<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

/** El logo se guarda en privado; se sirve solo si el plan de la empresa incluye marca propia. */
class CompanyLogoController extends Controller
{
    public function __invoke(Company $company)
    {
        abort_unless($company->logo && $company->hasFeature('branding') && Storage::disk('local')->exists($company->logo), 404);

        return Storage::disk('local')->response($company->logo, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
