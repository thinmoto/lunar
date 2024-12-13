<?php

namespace Lunar\Hub\Http\Livewire\Components\Products\Tables;

use Illuminate\Support\Collection;
use Lunar\Facades\DB;
use Lunar\Hub\Http\Livewire\Traits\Notifies;
use Lunar\Hub\Models\SavedSearch;
use Lunar\Hub\Tables\Builders\ProductsTableBuilder;
use Lunar\LivewireTables\Components\Actions\Action;
use Lunar\LivewireTables\Components\Actions\BulkAction;
use Lunar\LivewireTables\Components\Columns\BadgeColumn;
use Lunar\LivewireTables\Components\Columns\ImageColumn;
use Lunar\LivewireTables\Components\Columns\TextColumn;
use Lunar\LivewireTables\Components\Filters\CheckboxFilter;
use Lunar\LivewireTables\Components\Filters\SelectFilter;
use Lunar\LivewireTables\Components\Table;
use Lunar\Models\Brand;
use Lunar\Models\Product;

class ProductsTable extends Table
{
    use Notifies;

    /**
     * {@inheritDoc}
     */
    protected $tableBuilderBinding = ProductsTableBuilder::class;

    /**
     * {@inheritDoc}
     */
    public bool $searchable = true;

    /**
     * {@inheritDoc}
     */
    public bool $canSaveSearches = true;

    /**
     * {@inheritDoc}
     */
    protected $listeners = [
        'saveSearch' => 'handleSaveSearch',
    ];

    /**
     * {@inheritDoc}
     */
    public function build()
    {
        $products = Product::query()->withTrashed()->whereNotNull('deleted_at')->get();

        ini_set('max_execution_time', 0);

        foreach ($products as $product)
        {
            $variants = $product->variants;

            foreach($variants as $variant)
            {
                // $variant->values()->detach();

                DB::table('lunar_product_option_value_product_variant')->where('variant_id', $variant->id)->delete();

                foreach($variant->prices as $price)
                    $price->delete();

                $variant->delete();

                DB::table('lunar_product_variants')->where('id', $variant->id)->delete();
            }

            $product->delete();

            DB::table('lunar_product_associations')->where('product_parent_id', $product->id)->delete();
            DB::table('lunar_product_associations')->where('product_target_id', $product->id)->delete();
            DB::table('lunar_collection_product')->where('product_id', $product->id)->delete();
            DB::table('lunar_customer_group_product')->where('product_id', $product->id)->delete();
            DB::table('lunar_products')->where('id', $product->id)->delete();
        }

        $this->tableBuilder->addFilter(
            SelectFilter::make('status')->options(function () {
                $statuses = collect([
                    'published' => 'Published',
                    'draft' => 'Draft',
                ]);

                return collect([
                    null => 'All Statuses',
                ])->merge($statuses);
            })->query(function ($filters, $query) {
                $value = $filters->get('status');

                if ($value) {
                    $query->whereStatus($value);
                }
            })
        );

        $this->tableBuilder->addFilter(
            SelectFilter::make('brand')->options(function () {
                $brands = Brand::query()->orderBy('name')->pluck('name', 'id');

                return collect([
                    null => 'All Brands',
                ])->union($brands);
            })->query(function ($filters, $query) {
                $value = $filters->get('brand');

                if ($value) {
                    $query->whereBrandId($value);
                }
            })
        );

        // $this->tableBuilder->addFilter(
        //     CheckboxFilter::make('deleted')->query(function ($filters, $query) {
        //         $value = $filters->get('deleted');
        //
        //         if ($value) {
        //             $query->onlyTrashed();
        //         }
        //     })
        // );

        $this->tableBuilder->baseColumns([
            BadgeColumn::make('status', function ($record) {
                return __(
                    'adminhub::components.products.index.'.($record->deleted_at ? 'deleted' : $record->status)
                );
            })->states(function ($record) {
                return [
                    'success' => $record->status == 'published' && ! $record->deleted_at,
                    'warning' => $record->status == 'draft' && ! $record->deleted_at,
                    'danger' => (bool) $record->deleted_at,
                ];
            }),
            ImageColumn::make('thumbnail', function ($record) {
                if ($record->thumbnail) {
                    return $record->thumbnail->getUrl('small');
                }

                $variant = $record->variants->first(function ($variant) {
                    return $variant->thumbnail;
                });

                return $variant?->thumbnail?->getUrl('small');
            })->heading(false),
            TextColumn::make('name', function ($record) {
                return $record->translateAttribute('name');
            })->url(function ($record) {
                return route('hub.products.show', $record->id);
            })->heading(
                __('adminhub::tables.headings.name')
            ),
            TextColumn::make('brand.name')->heading(
                __('adminhub::tables.headings.brand')
            ),
            TextColumn::make('sku', function ($record) {
                $skus = $record->variants()->pluck('sku');

                if ($skus->count() > 1) {
                    return 'Multiple';
                }

                return $skus->first();
            })->heading(
                __('adminhub::tables.headings.sku')
            ),
            TextColumn::make('stock', function ($record) {
                return $record->variants()->sum('stock');
            })->heading(
                __('adminhub::tables.headings.stock')
            ),
            TextColumn::make('productType.name')->heading(
                __('adminhub::tables.headings.product_type')
            ),
        ]);

        $this->tableBuilder->addAction(
            Action::make('clone')
                ->label(__('adminhub::components.products.action-clone'))
                ->url(function ($record) {
                    return route('hub.products.clone', $record->id);
                })
        );
    }

    /**
     * Remove a saved search record.
     *
     * @param  int  $id
     * @return void
     */
    public function deleteSavedSearch($id)
    {
        SavedSearch::destroy($id);

        $this->resetSavedSearch();

        $this->notify(
            __('adminhub::notifications.saved_searches.deleted')
        );
    }

    /**
     * Save a search.
     *
     * @return void
     */
    public function saveSearch()
    {
        $this->validateOnly('savedSearchName', [
            'savedSearchName' => 'required',
        ]);

        auth()->getUser()->savedSearches()->create([
            'name' => $this->savedSearchName,
            'term' => $this->query,
            'component' => $this->getName(),
            'filters' => $this->filters,
        ]);

        $this->notify('Search saved');

        $this->savedSearchName = null;

        $this->emit('savedSearch');
    }

    /**
     * Return the saved searches available to the table.
     */
    public function getSavedSearchesProperty(): Collection
    {
        return auth()->getUser()->savedSearches()->whereComponent(
            $this->getName()
        )->get()->map(function ($savedSearch) {
            return [
                'key' => $savedSearch->id,
                'label' => $savedSearch->name,
                'filters' => $savedSearch->filters,
                'query' => $savedSearch->term,
            ];
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getData()
    {
        $filters = $this->filters;
        $query = $this->query;

        if ($this->savedSearch) {
            $search = $this->savedSearches->first(function ($search) {
                return $search['key'] == $this->savedSearch;
            });

            if ($search) {
                $filters = $search['filters'];
                $query = $search['query'];
            }
        }

        return $this->tableBuilder
            ->searchTerm($query)
            ->queryStringFilters($filters)
            ->perPage($this->perPage)
            ->getData();
    }
}
