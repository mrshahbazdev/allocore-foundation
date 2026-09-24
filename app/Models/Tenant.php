<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * ALLOCORE tenant (= Unternehmen / Mandant).
 *
 * Single-database tenancy (ADR-003): tenant data lives in the central
 * database and is scoped by `tenant_id` on every tenant-aware model via
 * Stancl\Tenancy\Database\Concerns\BelongsToTenant. No per-tenant
 * database is created (see TenancyServiceProvider).
 */
class Tenant extends BaseTenant
{
    use HasDomains;
}
