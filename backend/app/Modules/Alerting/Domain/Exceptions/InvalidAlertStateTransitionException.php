<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Exceptions;

use DomainException;

final class InvalidAlertStateTransitionException extends DomainException {}
