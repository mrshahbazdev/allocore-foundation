<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Person;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        return Person::with('company')
            ->when($request->company_id, fn ($q, $id) => $q->where('company_id', $id))
            ->when($request->q, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('first_name', 'like', '%'.$s.'%')
                    ->orWhere('last_name', 'like', '%'.$s.'%')
                    ->orWhere('email', 'like', '%'.$s.'%');
            }))
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['sometimes', Rule::in([Person::TYPE_EMPLOYEE, Person::TYPE_CONTACT, Person::TYPE_CONSULTANT])],
        ]);

        return response()->json(Person::create($validated), 201);
    }

    public function show(Person $person)
    {
        return $person->load('company');
    }

    public function update(Request $request, Person $person)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['sometimes', Rule::in([Person::TYPE_EMPLOYEE, Person::TYPE_CONTACT, Person::TYPE_CONSULTANT])],
        ]);

        $person->update($validated);

        return $person;
    }

    public function destroy(Person $person)
    {
        $person->delete();

        return response()->noContent();
    }
}
