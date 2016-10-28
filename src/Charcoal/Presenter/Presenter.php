<?php

namespace Charcoal\Presenter;

use InvalidArgumentException;
use ArrayAccess;
use Traversable;

/**
 * Presenter provides a presentation and transformation layer for a "model".
 *
 * It transforms (serializes) any data model (objects or array) into a presentation array, according to a **transformer**.
 *
 * A **transformer** defines the morph rules
 *
 * - A simple array or Traversable object, contain
 */
class Presenter
{
    /**
     * RegExp pattern for matching template tags.
     *
     * @const string
     */
    const MACRO_PATTERN = '~{{\s*(\w*?)\s*}}~';

    /**
     * @var callable $transformer
     */
    protected $transformer;

    /**
     * @var string $getterPattern
     */
    protected $getterPattern;

    /**
     * Return a new presenter object.
     *
     * @param mixed  $transformer   The data-view transformation array (or Traversable) object.
     * @param string $getterPattern The string pattern to match string with. Must have a single catch-block.
     */
    public function __construct($transformer, $getterPattern = self::MACRO_PATTERN)
    {
        $this->setTransformer($transformer);
        $this->getterPattern = $getterPattern;
    }

    /**
     * Set the data-to-view transformer.
     *
     * @param  mixed $transformer The transformation array (or Traversable) object.
     * @throws InvalidArgumentException If the provided transformer is not valid.
     * @return void
     */
    protected function setTransformer($transformer)
    {
        if (is_callable($transformer)) {
            $this->transformer = $transformer;
        } elseif ($this->isTraversable($transformer)) {
            $this->transformer = function ($model) use ($transformer) {
                return $transformer;
            };
        } else {
            throw new InvalidArgumentException(
                'Transformer must be an array or a Traversable object'
            );
        }
    }

    /**
     * Transform the given object.
     *
     * Its purpose is to transform a model (object) into view-data.
     *
     * The transformer is set from the constructor.
     *
     * @param  mixed $obj The original data (object / model) to transform into view-data.
     * @return array Normalized data, suitable as presentation (view) layer
     */
    public function transform($obj)
    {
        $transformer = $this->transformer;

        return $this->transmogrify($obj, $transformer($obj));
    }

    /**
     * Transmogrify an object into an other structure.
     *
     * @param  mixed $obj Source object.
     * @param  mixed $val Modifier.
     * @throws InvalidArgumentException If the modifier is not callable, traversable (array) or string.
     * @return mixed The transformed data (type depends on modifier).
     */
    protected function transmogrify($obj, $val)
    {
        // Arrays or traversables are handled recursively.
        // This also converts / casts any Traversable into a simple array.
        if ($this->isTraversable($val)) {
            $data = [];
            foreach ($val as $k => $v) {
                if (!is_string($k) && is_string($v)) {
                    $k = $v;
                    $v = $this->objectGet($obj, $v);
                }

                $data[$k] = $this->transmogrify($obj, $v);
            }

            return $data;
        }

        // Poor-man's Stringable
        if (method_exists($val, '__toString')) {
            $val = strval($val);
        }

        // Callables must accept the source object as an argument.
        if (!is_string($val) && is_callable($val)) {
            return $val($obj);
        }

        if (is_null($val)) {
            return '';
        }

        if (is_bool($val)) {
            return $val;
        }

        if (is_numeric($val)) {
            return $val;
        }

        // Strings are handled by rendering `{{property}}` with dynamic object getter.
        if (is_string($val)) {
            return $this->renderPattern($obj, $val);
        }

        // Any other
        throw new InvalidArgumentException(
            sprintf(
                'Transmogrify value needs to be callable, traversable (array), or stringable (string); "%s" given.',
                (is_object($val) ? get_class($val) : gettype($val))
            )
        );
    }

    /**
     * Simple pattern renderer.
     *
     * This method tries to fetch "tokens" from a given string and resolve their value
     * from the object being presented.
     *
     * @param  mixed  $obj     The model (object or array) to retrieve the property's value from.
     * @param  string $pattern The string containing tokens to render.
     * @return string The rendered pattern.
     */
    protected function renderPattern($obj, $pattern)
    {
        return preg_replace_callback(
            $this->getterPattern,
            function(array $matches) use ($obj) {
                return $this->objectGet($obj, $matches[1]);
            },
            $pattern
        );
    }

    /**
     * Get a value from an array or object.
     *
     * @param  mixed  $obj  The model (object or array) to retrieve the property's value from.
     * @param  string $attr The attribute to retrieve from the model.
     *     This could be a method, a public property, or an array index.
     * @return mixed If available, returns the attribute's value. Otherwise, the attribute name, unchanged.
     */
    protected function objectGet($obj, $attr)
    {
        if ($this->isArrayable($obj) && isset($obj[$attr])) {
            return $obj[$attr];
        }

        if (is_object($obj) && isset($obj->{$attr})) {
            return $obj->{$attr};
        }

        $method = [ $obj, $attr ];
        if (is_callable($method)) {
            return call_user_func($method);
        }

        return $attr;
    }

    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed $value The variable being evaluated.
     * @return boolean Returns TRUE if var is an arrayable, FALSE otherwise.
     */
    protected function isArrayable($value)
    {
        return (is_array($value) || $value instanceof ArrayAccess);
    }

    /**
     * Determine whether the given value is array traversable.
     *
     * @param  mixed $value The variable being evaluated.
     * @return boolean Returns TRUE if var is an traversable, FALSE otherwise.
     */
    protected function isTraversable($value)
    {
        return (is_array($value) || $value instanceof Traversable);
    }
}
