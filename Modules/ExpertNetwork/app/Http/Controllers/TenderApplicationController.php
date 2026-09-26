<?php

namespace Modules\ExpertNetwork\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Core\Notifications\Assigned;
use Modules\ExpertNetwork\Models\Tender;
use Modules\ExpertNetwork\Models\TenderApplication;

class TenderApplicationController extends Controller
{
    public function store(Request $request, Tender $tender)
    {
        abort_if($tender->status !== Tender::STATUS_OPEN, 422, 'Tender ist nicht offen.');

        $validated = $request->validate([
            'expert_profile_id' => ['required', 'exists:expert_profiles,id'],
            'proposal' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $application = $tender->applications()->create($validated);

        if ($tender->created_by && (int) $tender->created_by !== (int) $request->user()->id) {
            $expert = $application->expertProfile?->person;
            $expertName = $expert ? trim($expert->first_name.' '.$expert->last_name) : '';
            User::find($tender->created_by)?->notify(new Assigned(
                'ausschreibung',
                $tender->id,
                'Neue Bewerbung von '.($expertName !== '' ? $expertName : 'einem Experten').' für "'.$tender->title.'"',
                $tender->deadline_at,
            ));
        }

        return response()->json($application, 201);
    }

    public function update(Request $request, TenderApplication $tenderApplication)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                TenderApplication::STATUS_SHORTLISTED,
                TenderApplication::STATUS_AWARDED,
                TenderApplication::STATUS_REJECTED,
            ])],
        ]);

        $tenderApplication->update($validated);

        if ($validated['status'] === TenderApplication::STATUS_AWARDED) {
            $tenderApplication->tender->update(['status' => Tender::STATUS_AWARDED]);
        }

        return $tenderApplication;
    }

    public function destroy(TenderApplication $tenderApplication)
    {
        $tenderApplication->delete();

        return response()->noContent();
    }
}
