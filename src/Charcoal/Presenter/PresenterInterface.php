<?php

namespace Charcoal\Presenter;

/**
 * Defines a presenter pattern, coordinating and communicating between the view and the model.
 */
interface PresenterInterface
{
    /**
     * Transmogrify this presenter given the context.
     *
     * @param  mixed $context Array or object transforming context.
     * @return string|array Returns the transmogrified presenter.
     */
    public function transform($context);
}
