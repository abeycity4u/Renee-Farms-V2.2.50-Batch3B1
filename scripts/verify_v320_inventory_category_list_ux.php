<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$inventory =
    file_get_contents(
        $root . '/inventory.php'
    );

$failures = 0;

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$failures): void {
        if ($condition) {
            echo "PASS: {$label}\n";
            return;
        }

        echo "FAIL: {$label}\n";
        $failures++;
    };

$check(
    str_contains(
        $inventory,
        '<div class="modal-dialog modal-xl">'
    ),
    'category manager uses extra-large modal'
);

$check(
    str_contains(
        $inventory,
        '<div class="col-lg-4">'
    ),
    'category form uses four-column desktop width'
);

$check(
    str_contains(
        $inventory,
        '<div class="col-lg-8">'
    ),
    'category list uses eight-column desktop width'
);

$check(
    str_contains(
        $inventory,
        "<?php echo count(\$categories); ?>"
    ),
    'category list shows visible category count'
);

$check(
    str_contains(
        $inventory,
        'Categories assigned to inventory items cannot be deleted.'
    ),
    'category delete guidance is visible'
);

$check(
    str_contains(
        $inventory,
        'style="max-height:460px;overflow:auto;"'
    ),
    'category list has taller scroll area'
);

$check(
    str_contains(
        $inventory,
        '<thead class="position-sticky top-0" style="z-index:2;">'
    ),
    'category header remains visible while scrolling'
);

$check(
    str_contains(
        $inventory,
        '<tr class="text-nowrap">'
    ),
    'category column labels do not wrap'
);

$check(
    str_contains(
        $inventory,
        'name="category_financial_type" class="form-select form-select-sm" style="min-width:210px;"'
    ),
    'Financial Type selector has usable width'
);

$check(
    str_contains(
        $inventory,
        'name="category_inventory_role" class="form-select form-select-sm" style="min-width:220px;"'
    ),
    'Inventory Role selector has usable width'
);

$check(
    str_contains(
        $inventory,
        "if (isset(\$_POST['update_category_financial_type']))"
    ),
    'Financial Type update behavior remains present'
);

$check(
    str_contains(
        $inventory,
        "if (isset(\$_POST['update_category_inventory_role']))"
    ),
    'Inventory Role update behavior remains present'
);

$check(
    str_contains(
        $inventory,
        "if (isset(\$_POST['delete_category']))"
    ),
    'category delete behavior remains present'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "INVENTORY_CATEGORY_LIST_UX=PASS\n";
    exit(0);
}

echo "INVENTORY_CATEGORY_LIST_UX=FAIL\n";
exit(1);
