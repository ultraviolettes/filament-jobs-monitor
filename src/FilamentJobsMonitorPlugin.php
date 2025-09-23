<?php

namespace Croustibat\FilamentJobsMonitor;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;

class FilamentJobsMonitorPlugin implements Plugin
{
    use EvaluatesClosures;

    /**
     * The resource label.
     */
    protected string|Closure|null $label = null;

    /**
     * The plural resource label.
     */
    protected string|Closure|null $pluralLabel = null;

    /**
     * The resource navigation status.
     */
    protected bool|Closure $navigation = true;

    /**
     * The resource cluster.
     */
    protected ?string $cluster = null;

    /**
     * The resource navigation group.
     */
    protected string|Closure|null $navigationGroup = null;

    /**
     * The resource navigation icon.
     */
    protected ?string $navigationIcon = null;

    /**
     * The resource navigation sorting order.
     */
    protected ?int $navigationSort = null;

    /**
     * The resource navigation count badge status.
     */
    protected ?bool $navigationCountBadge = null;

    /**
     * The pruning status.
     */
    protected ?bool $pruning = null;

    /**
     * The pruning retention.
     */
    protected ?int $pruningRetention = null;

    /**
     * The resource class.
     */
    protected ?string $resource = null;

    /**
     * Get the plugin identifier.
     */
    public function getId(): string
    {
        return 'filament-jobs-monitor';
    }

    /**
     * Register the plugin.
     */
    public function register(Panel $panel): void
    {
        $panel->resources([
            $this->getResource(),
        ]);
    }

    /**
     * Boot the plugin.
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Make a new instance of the plugin.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Get the plugin instance.
     */
    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    /**
     * Get the resource class.
     */
    public function getResource(): string
    {
        return $this->resource ?? config('filament-jobs-monitor.resources.resource');
    }

    /**
     * Set the resource class.
     */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Get the resource label.
     */
    public function getLabel(): ?string
    {
        return $this->evaluate($this->label) ?? config('filament-jobs-monitor.resources.label');
    }

    /**
     * Set the resource label.
     */
    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get the plural resource label.
     */
    public function getPluralLabel(): ?string
    {
        return $this->evaluate($this->pluralLabel) ?? config('filament-jobs-monitor.resources.plural_label');
    }

    /**
     * Set the plural resource label.
     */
    public function pluralLabel(string|Closure $pluralLabel): static
    {
        $this->pluralLabel = $pluralLabel;

        return $this;
    }

    /**
     * Get the resource cluster name.
     */
    public function getClusterName(): ?string
    {
        return config('filament-jobs-monitor.resources.cluster');
    }

    /**
     * Get the resource navigation group.
     */
    public function getNavigationGroup(): ?string
    {
        return $this->evaluate($this->navigationGroup) ?? config('filament-jobs-monitor.resources.navigation_group');
    }

    /**
     * Set the resource navigation group.
     */
    public function navigationGroup(string|Closure $navigationGroup): static
    {
        $this->navigationGroup = $navigationGroup;

        return $this;
    }

    /**
     * Get the resource icon.
     */
    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon ?? config('filament-jobs-monitor.resources.navigation_icon');
    }

    /**
     * Set the resource icon.
     */
    public function navigationIcon(string $navigationIcon): static
    {
        $this->navigationIcon = $navigationIcon;

        return $this;
    }

    /**
     * Get the resource sort.
     */
    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('filament-jobs-monitor.resources.navigation_sort');
    }

    /**
     * Set the resource sort.
     */
    public function navigationSort(int $navigationSort): static
    {
        $this->navigationSort = $navigationSort;

        return $this;
    }

    /**
     * Get the resource navigation count badge status.
     */
    public function getNavigationCountBadge(): ?bool
    {
        return $this->navigationCountBadge ?? config('filament-jobs-monitor.resources.navigation_count_badge');
    }

    /**
     * Set the resource navigation count badge status.
     */
    public function navigationCountBadge(bool $navigationCountBadge = true): static
    {
        $this->navigationCountBadge = $navigationCountBadge;

        return $this;
    }

    /**
     * Determine whether the resource navigation is enabled.
     */
    public function shouldRegisterNavigation(): bool
    {
        return (($this->evaluate($this->navigation)) ?? config('filament-jobs-monitor.resources.enabled')) === true ;
    }

    /**
     * Enable the resource navigation.
     */
    public function enableNavigation(bool|Closure $callback = true): static
    {
        $this->navigation = $callback;

        return $this;
    }

    /**
     * Get the pruning status.
     */
    public function getPruning(): ?bool
    {
        return $this->pruning ?? config('filament-jobs-monitor.pruning.enabled');
    }

    /**
     * Set the pruning status.
     */
    public function enablePruning(bool $status = true): static
    {
        $this->pruning = $status;

        return $this;
    }

    /**
     * Get the pruning retention.
     */
    public function getPruningRetention(): ?int
    {
        return $this->pruningRetention ?? config('filament-jobs-monitor.pruning.retention_days');
    }

    /**
     * Set the pruning retention.
     */
    public function pruningRetention(int $pruningRetention): static
    {
        $this->pruningRetention = $pruningRetention;

        return $this;
    }

    /**
     * Get the resource breadcrumb.
     */
    public function getBreadcrumb(): string
    {
        return __('filament-jobs-monitor::translations.breadcrumb');
    }
}
