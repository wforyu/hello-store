<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index()
    {
        $addresses = auth()->user()->addresses()->latest()->get();

        return view('store.addresses', compact('addresses'));
    }

    public function create()
    {
        return view('store.address-form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'recipient' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'notes' => 'nullable|string|max:255',
            'is_default' => 'boolean',
        ]);

        $validated['user_id'] = auth()->id();

        if ($validated['is_default'] ?? false) {
            auth()->user()->addresses()->update(['is_default' => false]);
        }

        Address::create($validated);

        return redirect()->route('addresses.index')->with('success', 'Alamat berhasil ditambahkan!');
    }

    public function edit(Address $address)
    {
        if ($address->user_id !== auth()->id()) {
            abort(403);
        }

        return view('store.address-form', compact('address'));
    }

    public function update(Request $request, Address $address)
    {
        if ($address->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'recipient' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'notes' => 'nullable|string|max:255',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            auth()->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return redirect()->route('addresses.index')->with('success', 'Alamat berhasil diperbarui!');
    }

    public function destroy(Address $address)
    {
        if ($address->user_id !== auth()->id()) {
            abort(403);
        }
        $address->delete();

        return redirect()->route('addresses.index')->with('success', 'Alamat berhasil dihapus!');
    }
}
