<?php
/**
 * Inventory Office (canvasser workspace) — office name check.
 * Matches official names such as "Office of the Inventory Office" or "Inventory Office".
 */
function cwirms_is_office_of_inventory_office(?string $officeName): bool
{
    $d = strtolower(trim((string) $officeName));
    if ($d === '') {
        return false;
    }

    return strpos($d, 'office of the inventory office') !== false
        || strpos($d, 'inventory office') !== false;
}
