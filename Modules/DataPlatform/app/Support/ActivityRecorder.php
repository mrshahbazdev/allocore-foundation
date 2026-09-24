<?php

namespace Modules\DataPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Modules\Compliance\Models\Deadline;
use Modules\Compliance\Models\Inspection;
use Modules\Compliance\Models\Instruction;
use Modules\Compliance\Models\OperatingInstruction;
use Modules\Compliance\Models\RiskAssessment;
use Modules\Core\Models\Company;
use Modules\Core\Models\Person;
use Modules\CorporateDev\Models\Measure;
use Modules\CorporateDev\Models\Project;
use Modules\CorporateDev\Models\Strategy;
use Modules\DataPlatform\Events\DomainEvent;
use Modules\Documents\Models\Document;
use Modules\ExpertNetwork\Models\Answer;
use Modules\ExpertNetwork\Models\ExpertProfile;
use Modules\ExpertNetwork\Models\Question;
use Modules\ExpertNetwork\Models\Tender;
use Modules\ExpertNetwork\Models\TenderApplication;
use Modules\Production\Models\Machine;
use Modules\Production\Models\ProductionOrder;
use Modules\Investments\Models\Investment;
use Modules\Investments\Models\Portfolio;
use Modules\Tasks\Models\Task;

/**
 * R5 Event First: records every create/update/delete on platform domain
 * models as a DomainEvent in the central stored_events table.
 */
class ActivityRecorder
{
    /** model class => event-type prefix */
    public const WATCHED = [
        Company::class => 'company',
        Person::class => 'person',
        Document::class => 'document',
        Task::class => 'task',
        Instruction::class => 'instruction',
        Inspection::class => 'inspection',
        Deadline::class => 'deadline',
        RiskAssessment::class => 'risk_assessment',
        OperatingInstruction::class => 'operating_instruction',
        ExpertProfile::class => 'expert_profile',
        Question::class => 'question',
        Answer::class => 'answer',
        Tender::class => 'tender',
        TenderApplication::class => 'tender_application',
        Machine::class => 'machine',
        ProductionOrder::class => 'production_order',
        Portfolio::class => 'portfolio',
        Investment::class => 'investment',
        Strategy::class => 'strategy',
        Project::class => 'project',
        Measure::class => 'measure',
    ];

    public static function register(): void
    {
        foreach (self::WATCHED as $class => $prefix) {
            foreach (['created', 'updated', 'deleted'] as $action) {
                Event::listen("eloquent.{$action}: {$class}", function (Model $model) use ($prefix, $action) {
                    $event = new DomainEvent(
                        type: "{$prefix}.{$action}",
                        tenantId: (string) ($model->tenant_id ?? ''),
                        subject: [
                            'type' => $model->getMorphClass(),
                            'id' => $model->getKey(),
                            'title' => $model->title ?? $model->name ?? null,
                        ],
                        payload: $action === 'updated' ? $model->getChanges() : $model->getAttributes(),
                    );

                    $event->setMetaData(['tenant_id' => (string) ($model->tenant_id ?? '')]);

                    event($event);
                });
            }
        }
    }
}
