<?php

namespace App\Core\Quotes\Http\Controllers;

use App\Core\Quotes\Http\Requests\StoreServiceCatalogItemRequest;
use App\Core\Quotes\Http\Requests\UpdateServiceCatalogItemRequest;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCatalogItemController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ServiceCatalogItem::class);

        return Inertia::render('Quotes/ServiceCatalog/Index', [
            'items' => ServiceCatalogItem::query()->orderBy('name')->get(),
            'canManage' => request()->user()->can('create', ServiceCatalogItem::class),
        ]);
    }

    public function store(StoreServiceCatalogItemRequest $request): RedirectResponse
    {
        ServiceCatalogItem::create($request->validated());

        return back()->with('success', 'Voce di listino aggiunta.');
    }

    public function update(UpdateServiceCatalogItemRequest $request, ServiceCatalogItem $serviceCatalogItem): RedirectResponse
    {
        $serviceCatalogItem->update($request->validated());

        return back()->with('success', 'Voce di listino aggiornata.');
    }
}
