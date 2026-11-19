<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\FormTemplate;
use App\Models\ServiceCategory;
use App\Services\Forms\FormSchema;
use App\Services\Forms\FormTemplates;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Checklist templates and their versions (Forms-Approvals-Batch spec FRM-01 to FRM-03).
 * The route group carries level:form.manage and the `forms` plan gate.
 */
class FormTemplatesController extends Controller
{
    private function tenant(): Tenant
    {
        return Tenant::findOrFail(auth()->user()->tenant_id);
    }

    public function index()
    {
        $tenant    = $this->tenant();
        $templates = FormTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->with('versions')->orderBy('name')->get();
        $tid       = $tenant->id;

        return view('admin.checklists.index', [
            'templates'  => $templates,
            'limit'      => app(FormTemplates::class)->limit($tenant),
            'categories' => ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tid)->whereNotNull('active_form_template_id')->get()->groupBy('active_form_template_id'),
            'assets'     => Asset::withoutGlobalScopes()->where('tenant_id', $tid)->whereNotNull('form_template_id')->get()->groupBy('form_template_id'),
        ]);
    }

    public function create()
    {
        return view('admin.checklists.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $tenant = $this->tenant();
        if (FormTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', $data['name'])->exists()) {
            return back()->withInput()->withErrors(['name' => 'A checklist with this name already exists.']);
        }

        $template = app(FormTemplates::class)->create($tenant, $request->user(), $data['name'], $data['description'] ?? null);

        return redirect()->route('admin.checklists.show', $template)->with('success', "Checklist {$template->name} created.");
    }

    public function show(FormTemplate $template)
    {
        $draft     = $template->draftVersion();
        $published = $template->publishedVersion();

        return view('admin.checklists.builder', [
            'template'  => $template, 'draft' => $draft, 'published' => $published,
            'schema'    => ($draft ?? $published)?->schema_json ?? FormSchema::blank(),
            'readonly'  => ! $draft,
            'types'     => FormSchema::TYPES, 'ops' => FormSchema::OPS,
            'versions'  => $template->versions,
        ]);
    }

    public function saveDraft(Request $request, FormTemplate $template)
    {
        $data = $request->validate(['schema' => ['required', 'string']]);
        $schema = json_decode($data['schema'], true);
        if (! is_array($schema)) {
            return back()->with('error', 'The form could not be read. Reload the page and try again.');
        }
        app(FormTemplates::class)->saveDraft($template, $schema);

        return back()->with('success', 'Draft saved.');
    }

    public function editDraft(FormTemplate $template)
    {
        app(FormTemplates::class)->editAsNewDraft($template);

        return redirect()->route('admin.checklists.show', $template);
    }

    public function publish(Request $request, FormTemplate $template)
    {
        // The page saves the draft first when it posts the schema along with the publish button.
        if ($request->filled('schema') && ($schema = json_decode($request->input('schema'), true)) && is_array($schema)) {
            app(FormTemplates::class)->saveDraft($template, $schema);
        }
        $v = app(FormTemplates::class)->publish($template, $request->user());

        return redirect()->route('admin.checklists.show', $template)->with('success', "Version {$v->version_no} is published.");
    }

    public function discardDraft(FormTemplate $template)
    {
        app(FormTemplates::class)->discardDraft($template);

        return redirect()->route('admin.checklists.index')->with('success', 'Draft discarded.');
    }

    public function archive(FormTemplate $template)
    {
        app(FormTemplates::class)->archive($template);

        return redirect()->route('admin.checklists.index')->with('success', "{$template->name} archived.");
    }

    public function duplicate(Request $request, FormTemplate $template)
    {
        $copy = app(FormTemplates::class)->duplicate($this->tenant(), $request->user(), $template);

        return redirect()->route('admin.checklists.show', $copy)->with('success', "Copied to {$copy->name}.");
    }

    public function destroy(FormTemplate $template)
    {
        app(FormTemplates::class)->delete($template);

        return redirect()->route('admin.checklists.index')->with('success', 'Checklist deleted.');
    }
}
