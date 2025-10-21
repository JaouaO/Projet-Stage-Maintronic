<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterventionRequest extends FormRequest
{
    public function authorize()
    {
        return true; // tu as déjà le middleware de session
    }

    public function rules()
    {
        return [
            'commentaire'   => ['nullable','string','max:1000'],
            'contact_reel'  => ['nullable','string','max:255'],

            'rea_sal'       => ['required','string','max:5','exists:t_salarie,CodeSal'],
            'date_rdv'  => ['date_format:Y-m-d','required_with:heure_rdv','after_or_equal:today'],
            'heure_rdv' => ['date_format:H:i','required_with:date_rdv'],

            'code_sal_auteur' => ['required','string','max:5'],

            // adapte les tailles à ton schéma (5 pour CP FR, 80 pour ville/marque par ex.)
            'code_postal'   => ['nullable','string','max:10','regex:/^[0-9A-Za-z\- ]{4,10}$/'],
            'ville'         => ['nullable','string','max:80'],
            'marque'        => ['nullable','string','max:80'],
            'objet_trait'   => ['nullable','string','max:120'],

            'traitement'    => ['array'],
            'traitement.*'  => ['in:0,1'],
            'affectation'   => ['array'],
            'affectation.*' => ['in:0,1'],

            'rdv_validated_by_ajax' => ['nullable','in:0,1'],
            'action_type'   => ['nullable','in:,call,validate_rdv'],
        ];
    }

    public function messages()
    {
        return [
            'date_rdv.required_with'  => 'La date est requise si l’heure est fournie.',
            'heure_rdv.required_with' => 'L’heure est requise si la date est fournie.',
        ];
    }
}
