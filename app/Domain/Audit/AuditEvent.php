<?php

namespace App\Domain\Audit;

use App\Support\EnumHelpers;

enum AuditEvent: string
{
    use EnumHelpers;

    case LEAD_ASSIGNED = 'LEAD_ASSIGNED';
    case LEAD_REASSIGNED = 'LEAD_REASSIGNED';
    case LEAD_UNASSIGNED = 'LEAD_UNASSIGNED';
    case LEADS_IMPORTED = 'LEADS_IMPORTED';
    case CALL_STARTED = 'CALL_STARTED';
    case CALL_ENDED = 'CALL_ENDED';
    case QUALIFICATION_STARTED = 'QUALIFICATION_STARTED';
    case QUALIFICATION_COMPLETED = 'QUALIFICATION_COMPLETED';
    case STATUS_CHANGED = 'STATUS_CHANGED';
    case SCORE_CHANGED = 'SCORE_CHANGED';
    case CALLBACK_SCHEDULED = 'CALLBACK_SCHEDULED';
    case NRP_REGISTERED = 'NRP_REGISTERED';
    case NOTE_ADDED = 'NOTE_ADDED';
    case USER_REGISTERED = 'USER_REGISTERED';
    case USER_APPROVED = 'USER_APPROVED';
    case USER_REJECTED = 'USER_REJECTED';
    case USER_DEACTIVATED = 'USER_DEACTIVATED';
    case USER_REACTIVATED = 'USER_REACTIVATED';
    case USER_DELETED = 'USER_DELETED';
    case SCRIPT_UPDATED = 'SCRIPT_UPDATED';
    case LEAD_REPORT_EXPORTED = 'LEAD_REPORT_EXPORTED';

    public function label(): string
    {
        return match ($this) {
            self::LEAD_ASSIGNED => 'Lead assigné',
            self::LEAD_REASSIGNED => 'Lead réassigné',
            self::LEAD_UNASSIGNED => 'Lead désassigné',
            self::LEADS_IMPORTED => 'Import de leads',
            self::CALL_STARTED => 'Appel démarré',
            self::CALL_ENDED => 'Appel terminé',
            self::QUALIFICATION_STARTED => 'Qualification démarrée',
            self::QUALIFICATION_COMPLETED => 'Qualification terminée',
            self::STATUS_CHANGED => 'Statut modifié',
            self::SCORE_CHANGED => 'Score modifié',
            self::CALLBACK_SCHEDULED => 'Rappel programmé',
            self::NRP_REGISTERED => 'NRP enregistré',
            self::NOTE_ADDED => 'Note ajoutée',
            self::USER_REGISTERED => 'Inscription',
            self::USER_APPROVED => 'Compte approuvé',
            self::USER_REJECTED => 'Inscription refusée',
            self::USER_DEACTIVATED => 'Compte désactivé',
            self::USER_REACTIVATED => 'Compte réactivé',
            self::USER_DELETED => 'Compte supprimé (archivé)',
            self::SCRIPT_UPDATED => 'Script modifié',
            self::LEAD_REPORT_EXPORTED => 'Fiche PDF générée',
        };
    }
}
