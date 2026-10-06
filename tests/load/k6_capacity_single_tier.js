import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const rawBaseUrl = (__ENV.K6_BASE_URL || '').trim();
const baseUrl = rawBaseUrl.replace(/\/$/, '');

const authorization = (__ENV.K6_ALLOW_ISOLATED_CAPACITY || '').trim();
const targetClass = (__ENV.K6_CAPACITY_TARGET_CLASS || '').trim();

const cookieName = (__ENV.K6_SESSION_COOKIE_NAME || 'PHPSESSID').trim();

const vus = Number.parseInt(__ENV.K6_CAPACITY_VUS || '0', 10);
const holdDuration = (__ENV.K6_CAPACITY_DURATION || '2m').trim();

const allowedTiers = [25, 50, 75, 100, 125, 150, 200];

const paths = (__ENV.K6_PATHS ||
  '/dashboard.php,/inventory.php,/management/reports.php')
  .split(',')
  .map((value) => value.trim())
  .filter(Boolean);

const sessions = new SharedArray('isolated capacity sessions', () => {
  return (__ENV.K6_SESSION_COOKIES || '')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);
});

if (!baseUrl) {
  throw new Error('K6_BASE_URL is required.');
}

let parsedTarget;
try {
  parsedTarget = new URL(baseUrl);
} catch (_) {
  throw new Error('K6_BASE_URL must be a valid absolute URL.');
}

if (parsedTarget.protocol !== 'https:') {
  throw new Error('Capacity target must use HTTPS.');
}

const hostname = parsedTarget.hostname.toLowerCase();

const forbiddenHosts = new Set([
  'reneefarms.com',
  'www.reneefarms.com',
  'staging.reneefarms.com',
]);

if (forbiddenHosts.has(hostname)) {
  throw new Error(
    `Refusing capacity test against prohibited shared/production target: ${hostname}`
  );
}

if (authorization !== 'YES') {
  throw new Error(
    'K6_ALLOW_ISOLATED_CAPACITY=YES is required for capacity execution.'
  );
}

if (targetClass !== 'isolated') {
  throw new Error(
    'K6_CAPACITY_TARGET_CLASS=isolated is required.'
  );
}

if (!Number.isInteger(vus) || !allowedTiers.includes(vus)) {
  throw new Error(
    `K6_CAPACITY_VUS must be one approved tier: ${allowedTiers.join(', ')}`
  );
}

if (sessions.length === 0) {
  throw new Error(
    'K6_SESSION_COOKIES is required with disposable isolated-environment sessions.'
  );
}

if (sessions.length < 2) {
  throw new Error(
    'At least two independent authenticated sessions are required.'
  );
}

if (paths.length === 0) {
  throw new Error('At least one read-only K6_PATHS entry is required.');
}

for (const path of paths) {
  if (!path.startsWith('/')) {
    throw new Error(`Invalid relative path: ${path}`);
  }
}

export const options = {
  scenarios: {
    isolated_capacity_tier: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: vus },
        { duration: holdDuration, target: vus },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '15s',
    },
  },

  thresholds: {
    http_req_failed: [
      {
        threshold: 'rate<0.02',
        abortOnFail: true,
        delayAbortEval: '20s',
      },
    ],

    http_req_duration: [
      {
        threshold: 'p(95)<3000',
        abortOnFail: true,
        delayAbortEval: '30s',
      },
      {
        threshold: 'p(99)<7500',
        abortOnFail: true,
        delayAbortEval: '30s',
      },
    ],

    checks: [
      {
        threshold: 'rate>0.98',
        abortOnFail: true,
        delayAbortEval: '20s',
      },
    ],
  },
};

export default function () {
  const session = sessions[(__VU - 1) % sessions.length];

  const jar = http.cookieJar();

  jar.set(baseUrl, cookieName, session, {
    path: '/',
    secure: true,
    http_only: true,
  });

  for (const path of paths) {
    const response = http.get(`${baseUrl}${path}`, {
      redirects: 0,
      timeout: '15s',
      tags: {
        surface: path,
        capacity_tier: String(vus),
      },
    });

    check(response, {
      [`${path} returns 2xx/3xx`]:
        (r) => r.status >= 200 && r.status < 400,

      [`${path} has no server-failure signature`]:
        (r) => !/SQLSTATE\[|PDOException|Fatal error:|Stack trace:/i.test(
          r.body || ''
        ),
    });

    sleep(0.25);
  }

  sleep(0.75);
}

export function handleSummary(data) {
  return {
    stdout: JSON.stringify(
      {
        capacity_test: true,
        target_class: targetClass,
        target_host: hostname,
        tier_vus: vus,
        hold_duration: holdDuration,
        session_count: sessions.length,
        path_count: paths.length,
        metrics: data.metrics,
      },
      null,
      2
    ),
  };
}
