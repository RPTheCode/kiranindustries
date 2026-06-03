<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    use Concerns\LogsMasterCrud;

    public function index(Request $request)
    {
        $query = DocumentType::withPermissionCheck();

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle required filter
        if ($request->has('required') && $request->required !== 'all') {
            $isRequired = $request->required === 'yes';
            $query->where('is_required', $isRequired);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $documentTypes = $query->paginate($request->per_page ?? 10);

        // Cast is_required to boolean for each document type
        $documentTypes->getCollection()->transform(function ($documentType) {
            $documentType->is_required = (bool) $documentType->is_required;
            return $documentType;
        });

        return Inertia::render('hr/document-types/index', [
            'documentTypes' => $documentTypes,
            'filters' => $request->all(['search', 'sort_field', 'sort_direction', 'per_page', 'required']),
        ]);
    }


    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('document_types')->where(function ($query) {
                        return $query->whereIn('created_by', getCompanyAndUsersId());
                    })
                ],
                'description' => 'nullable|string',
                'is_required' => 'boolean',
            ]);

            $validated['created_by'] = creatorId();

            $documentType = DocumentType::create($validated);
            $this->logMasterCreated($documentType);
            return redirect()->back()->with('success', __('Document type created successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to create document type'));
        }
    }


    public function update(Request $request, $documentTypeId)
    {
        $documentType = DocumentType::where('id', $documentTypeId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($documentType) {

            try {
                $validated = $request->validate([
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('document_types')->where(function ($query) {
                            return $query->whereIn('created_by', getCompanyAndUsersId());
                        })->ignore($documentTypeId)
                    ],
                    'description' => 'nullable|string',
                    'is_required' => 'boolean',
                ]);

                $documentType->update($validated);
                $this->logMasterUpdated($documentType);
            return redirect()->back()->with('success', __('Document type updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update document type'));
            }
        } else {
            return redirect()->back()->with('error', __('Document Type Not Found.'));
        }
    }


    public function destroy($documentTypeId)
    {
        $documentType = DocumentType::where('id', $documentTypeId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($documentType) {
            try {
                $this->logMasterDeleted($documentType);
                $documentType->delete();
            return redirect()->back()->with('success', __('Document type deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete document type'));
            }
        } else {
            return redirect()->back()->with('error', __('Document Type Not Found.'));
        }
    }
}
