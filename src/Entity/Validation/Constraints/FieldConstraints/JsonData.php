<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Validation\Constraints\FieldConstraints;

use Symfony\Component\Validator\Constraint;

/**
 * This is a template constraint. The Constraint does very little other than specify a message and imply a
 * ConstraintValidator with the same FQN as this class, but with `Validator` appended.
 *
 * @see https://symfony.com/doc/2.6/cookbook/validation/custom_constraint.html
 *
 * Suggest using the `./bin/doctrine dsm:override:create` command to create a new
 * override straight after you generate the file.
 *
 * Then you can edit it and update your override as required using
 * `./bin/doctrine dsm:overrides:update -a fromProject`
 *
 * And then reapply after a new build using
 * `./bin/doctrine dsm:overrides:update -a toProject`
 *
 */
class JsonData extends Constraint
{
    public const MESSAGE = 'The value {{ string }} is not a valid JSON. Got the following error {{ error }} ';

    /**
     * @param string[] $groups
     */
    public function __construct($options = null, array $groups = null, $payload = self::MESSAGE)
    {
        parent::__construct($options, $groups, $payload);
    }

    /**
     * Returns whether the constraint can be put onto classes, properties or
     * both.
     *
     * This method should return one or more of the constants
     * self::CLASS_CONSTRAINT and self::PROPERTY_CONSTRAINT.
     *
     * @return string One or more constant values
     */
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
