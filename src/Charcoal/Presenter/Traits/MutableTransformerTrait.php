<?php

namespace Charcoal\Presenter\Traits;

use Traversable;
use InvalidArgumentException;

/**
 * Provides support to swap the output of a transforming context.
 *
 * To be used by instanceof {@see \Charcoal\Presenter\PresenterInterface}.
 */
trait MutableTransformerTrait
{
    /**
     * The presentation.
     *
     * @var callable
     */
    protected $transformer;

    /**
     * Set the model-to-view transformer.
     *
     * The transformer must lead to a plain array.
     *
     * The method accepts:
     * • a literal string or "dot" notation, e.g.: `display_date`
     * • a plain array, e.g.: `[ 'id', 'name', 'display_date' => 'entry_date' ]`
     * • a valid function, method, or invokable object (Closure, `__invoke()`)
     *   using the signature: `array callback ( mixed $context )`), e.g.:
     *   ```php
     *   function ($context) {
     *       return [ 'id', 'name', 'display_date' ];
     *   }
     *   ```
     *
     * @param  mixed $transformer The transformation array (or Traversable) object.
     * @throws InvalidArgumentException If the provided transformer is not valid.
     * @return self
     */
    public function setTransformer($transformer)
    {
        if ($this->useAsCallable($transformer)) {
            $this->transformer = $transformer;
        } elseif (
            is_string($transformer) || is_array($transformer) || $transformer instanceof Traversable
        ) {
            $this->transformer = function ($context) use ($transformer) {
                return $transformer;
            };
        } else {
            throw new InvalidArgumentException(
                'Transformer must be an array or a Traversable object'
            );
        }

        return $this;
    }

    /**
     * Retrieve the transformer's presentation layer.
     *
     * @param  mixed $context Array or object transforming context.
     * @return string|array Returns an associative array representing the presentation layer.
     */
    protected function transformer($context)
    {
        $transformer = $this->transformer;

        return $transformer($context);
    }
}
