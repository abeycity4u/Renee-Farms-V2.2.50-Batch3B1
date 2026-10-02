import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const baseUrl = (__ENV.K6_BASE_URL || '').replace(/\/$/, '');
const cookieName = __ENV.K6_SESSION_COOKIE_NAME || 'PHPSESSID';
const paths = (__ENV.K6_PATHS || '/dashboard.php,/inventory.php,/management/reports.php')
  .split(',')
  .map((value) => value.trim())
  .filter(Boolean);

const sessions = new SharedArray('staging sessions', () => {
  return (__ENV.K6_SESSION_COOKIES || '')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);
});

if (!baseUrl) {
  throw new Error('K6_BASE_URL is required and must point to isolated staging.');
}
if (sessions.length === 0) {
  throw new Error('K6_SESSION_COOKIES is required. Supply pre-authenticated disposable staging sessions.');
}

export const options = {
  scenarios: {
    farm_users: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 10 },
        { duration: '2m', target: 25 },
        { duration: '2m', target: 50 },
        { duration: '1m', target: 100 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '15s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<2000', 'p(99)<5000'],
    checks: ['rate>0.99'],
  },
};

export default function () {
  const session = sessions[(__VU - 1) % sessions.length];
  const jar = http.cookieJar();
  jar.set(baseUrl, cookieName, session, {
    path: '/',
    secure: baseUrl.startsWith('https://'),
    http_only: true,
  });

  for (const path of paths) {
    const response = http.get(`${baseUrl}${path}`, {
      redirects: 0,
      tags: { surface: path },
    });

    check(response, {
      [`${path} returns 2xx/3xx without server failure`]: (r) => r.status >= 200 && r.status < 400,
      [`${path} does not expose fatal signatures`]: (r) => !/SQLSTATE\[|PDOException|Fatal error:|Stack trace:/i.test(r.body || ''),
    });

    sleep(0.25);
  }

  sleep(0.75);
}
