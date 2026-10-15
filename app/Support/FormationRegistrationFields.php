<?php

namespace App\Support;

final class FormationRegistrationFields
{
    public static function forType(string $type): array
    {
        if (! in_array($type, ['Retreat', 'Recollection'], true)) {
            return [];
        }
        $event = strtolower($type);

        return [
            ['section' => 'Personal information', 'label' => 'Nickname', 'type' => 'text', 'required' => true],
            ['section' => 'Personal information', 'label' => 'Age', 'type' => 'number', 'required' => true],
            ['section' => 'Personal information', 'label' => 'Sex', 'type' => 'select', 'options' => ['Female', 'Male', 'Prefer not to say'], 'required' => true],
            ['section' => 'Emergency information', 'label' => 'Emergency contact person', 'type' => 'text', 'required' => true],
            ['section' => 'Emergency information', 'label' => 'Relationship to emergency contact', 'type' => 'text', 'required' => true],
            ['section' => 'Emergency information', 'label' => 'Emergency contact number', 'type' => 'text', 'required' => true],
            ['section' => 'Health and safety', 'label' => 'Medical conditions', 'type' => 'textarea', 'required' => false],
            ['section' => 'Health and safety', 'label' => 'Allergies', 'type' => 'textarea', 'required' => false],
            ['section' => 'Health and safety', 'label' => 'Medications currently taking', 'type' => 'textarea', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Religion', 'type' => 'text', 'required' => true],
            ['section' => 'Faith and sacramental background', 'label' => 'Parish, if any', 'type' => 'text', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Baptism received', 'type' => 'checkbox', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Holy Communion received', 'type' => 'checkbox', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Confirmation received', 'type' => 'checkbox', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Church or campus ministry involvement', 'type' => 'textarea', 'required' => false],
            ['section' => ucfirst($event).' experience', 'label' => "Is this your first Catholic {$event}?", 'type' => 'select', 'options' => ['Yes', 'No'], 'required' => true],
            ['section' => ucfirst($event).' experience', 'label' => 'Previous '.ucfirst($event).'s attended, if any', 'type' => 'textarea', 'required' => false],
            ['section' => 'Expectations and intentions', 'label' => "What do you hope to receive or experience during this {$event}?", 'type' => 'textarea', 'required' => true],
            ['section' => 'Personal reflection', 'label' => 'Prayer intentions, struggles, or concerns you wish to share', 'type' => 'textarea', 'required' => false],
            ['section' => 'Consent and commitment', 'label' => "I understand that this {$event} is a faith-based activity and I choose to participate with openness, respect, and confidentiality.", 'type' => 'checkbox', 'required' => true],
            ['section' => 'Consent and commitment', 'label' => "I understand that photos may be taken during this {$event} for University purposes.", 'type' => 'checkbox', 'required' => true],
        ];
    }
}
