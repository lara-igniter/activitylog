# Laraigniter activity log

Auditable activity logging for Laraigniter applications. It uses `App\Core\MY_Model`, CodeIgniter config groups, and the existing Laraigniter model-event arrays; it has no Laravel or Eloquent dependency.

## Installation

Install the package:

```powershell
composer require lara-igniter/activitylog
```

Add the provider to `config/hooks.php` before application providers:

```php
Laraigniter\Activitylog\ActivitylogServiceProvider::class,
```

### Publish and run the migration

The activity log table is required. Publish the package migration:

```powershell
php artisan vendor:publish --tag=activitylog-migrations
```

Then run the migration:

```powershell
php artisan migrate
```

### Publish the configuration (optional)

The package loads its bundled defaults automatically. Publish an editable application override only when needed:

```powershell
php artisan vendor:publish --tag=activitylog-config
```

The published `config/activitylog.php` is loaded after the package defaults and therefore overrides them. Use `--force` only when you intentionally want to overwrite an existing published file.

## Log an activity explicitly

Pass a Laraigniter model instance and the persisted subject ID. The framework's read results are data objects, so the ID is explicit when logging from a model service/repository.

```php
activity('payments')
    ->event('paid')
    ->performedOn($receiptModel, $receiptId)
    ->withProperty('amount', $amount)
    ->log('Receipt paid');
```

The authenticated Ion Auth user is attached automatically. Use `causedBy()` to supply another actor, or `causedByAnonymous()` to suppress it.

## Automatically log model events

Use `LogsActivity` in an `MY_Model` class and return its options:

```php
use Laraigniter\Activitylog\Models\Concerns\LogsActivity;
use Laraigniter\Activitylog\Support\LogOptions;

class Receipt extends MY_Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->logExcept(['internal_note'])
            ->dontLogEmptyChanges()
            ->useLogName('receipts')
            ->setDescriptionForEvent(static function (string $event): string {
                return "Receipt {$event}";
            });
    }
}
```

The trait records `created`, `updated`, `deleted`, `force_deleted`, and `restored` events. `deleted` represents a soft delete; `force_deleted` represents a permanent deletion. For a single-record update, it captures the persisted row before the write and stores Laravel-style changes: new values under `attribute_changes.attributes` and previous values under `attribute_changes.old`. With `logOnlyDirty()`, unchanged attributes are excluded from both collections.

### Polymorphic model context

For a logged model with polymorphic columns, declare the relationship prefix with `logMorphs()`. The type and ID are stored in the activity `properties.polymorphic_relations` collection, separately from the attribute changes.

```php
return LogOptions::defaults()
    ->logFillable()
    ->logMorphs(['addressable']); // addressable_type and addressable_id
```

Multiple relations are supported: `->logMorphs(['emailable', 'receiptable'])`. The activity-log UI can render every configured relation without model-specific conditions.

## Query logs and retention

```php
$activities = (new Laraigniter\Activitylog\Models\Activity())
    ->inLog('receipts')
    ->forEvent('updated')
    ->latest()
    ->all();

(new Laraigniter\Activitylog\Actions\CleanActivityLogAction())->execute();
```

### Cleanup command

Clean records older than the configured `clean_after_days` value:

```powershell
php artisan activitylog:clean
```

Override the retention period or clean one named log only:

```powershell
php artisan activitylog:clean --days=90
php artisan activitylog:clean receipts --days=90
```

Delete every activity record explicitly, or every record in one named log:

```powershell
php artisan activitylog:clean --all
php artisan activitylog:clean receipts --all
```

`--all` permanently deletes matching activity records and cannot be combined with `--days`.

Set `ACTIVITYLOG_ENABLED=false` to stop persistence. The default configuration excludes `password` and `remember_token`; extend `default_except_attributes` for additional sensitive fields.
