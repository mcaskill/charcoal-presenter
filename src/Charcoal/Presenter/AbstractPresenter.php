<?php

namespace Charcoal\Presenter;

use Closure;
use DateTimeInterface;
use JsonSerializable;
use ArrayAccess;
use Traversable;
use InvalidArgumentException;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection as IlluminateCollection;
use Charcoal\Config\ConfigInterface as CharcoalConfig;
use Charcoal\Model\CollectionInterface as CharcoalCollection;

/**
 * Abstract Presenter Pattern
 *
 * For coordinating and communicating between the view and the model. The class transforms (serializes)
 * any data model (object or array) into an associative array, according to a _presentation layer_ (a transformer).
 * The _transformer_ defines the values to extract from the context, morphing them into arrays and scalar values.
 *
 * @abstract
 */
abstract class AbstractPresenter implements PresenterInterface
{
    /**
     * Delimiter for accessing nested items.
     *
     * @var string
     */
    const ACCESSOR_SEPARATOR = '.';

    /**
     * Symbol for accessing through all items.
     *
     * @var string
     */
    const ACCESSOR_WILDCARD = '*';

    /**
     * Conversion symbol for resolving values from the presenter.
     *
     * @var string
     */
    const PRESENTER_ACCESSOR = '%';

    /**
     * Conversion symbol for resolving values from the transforming context.
     *
     * @var string
     */
    const CONTEXT_ACCESSOR = '$';

    /**
     * A generic date/time format.
     *
     * @var string
     */
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Presenter instances can be treated as a function and rendered by simply calling them.
     *
     *     $p = new MutablePresenter;
     *     $p->setTransformer([ 'id', 'name', 'display_date' => 'entry_date' ]);
     *     echo $p($data); // [ 'id' => 1, 'name' => 'World', 'entry_date' => '1970-01-01 00:00:00' ]
     *
     * @see AbstractPresenter::transform()
     *
     * @param  mixed $context Array or object transforming context.
     * @return string|array Returns the transmogrified presenter.
     */
    public function __invoke($context)
    {
        return $this->transform($context);
    }

    /**
     * Transmogrify this presenter given the context.
     *
     * @param  mixed $context Array or object transforming context.
     * @return string|array Returns the transmogrified presenter.
     */
    public function transform($context)
    {
        return $this->transmogrify($this->transformer($context), $context);
    }

    /**
     * The presentation layer implemented by concrete Presenter subclasses.
     *
     * @param  mixed $context Array or object transforming context.
     * @return array Returns an associative array representing the presentation layer.
     */
    abstract protected function transformer($context);

    /**
     * Transmogrify an array or object into a presentable array.
     *
     * This is where the magic happens :)
     *
     * Transmogrification features:
     *
     * • Each conversion specification must consist of a percent sign (%) or a dollar sign ($):
     *   • %v — will call a public method or retrieve an accessible value from the presenter.
     *   • $v – will call a public method or retrieve an accessible value from the context.
     * • An optional "dot" notation can be used to retrieve a value from a deeply nested array or object.
     *   The leading key will be used as the presentation key.
     *
     * @uses   AbstractPresenter::filterPair()
     * @param  mixed $value   The value to transform.
     * @param  mixed $context Array or object transforming context.
     * @throws InvalidArgumentException If the value is not scalar or an array.
     * @return string|array Returns the transmogrified context.
     */
    private function transmogrify($value, $context)
    {
        if (is_string($value)) {
            if ($value[0] === self::PRESENTER_ACCESSOR) {
                $target = $this;
            } elseif ($value[0] === self::CONTEXT_ACCESSOR) {
                $target = $context;
            }

            if (isset($target)) {
                $value = $this->dataGet($target, substr($value, 1));
                $value = $this->transmogrify($value, $context);
            }

            return $value;
        }

        if (is_array($value) || $value instanceof Traversable) {
            $results = [];
            foreach ($value as $k => $v) {
                if (!$this->filterPair($k, $v, $context)) {
                    throw new UnexpectedValueException(
                        'A transmogrifiable value must start with a percent sign (%s) or a dollar sign (%s). '.
                        'If the value is meant to be a literal string, a string key is required.',
                        self::PRESENTER_ACCESSOR,
                        self::CONTEXT_ACCESSOR
                    );
                }

                $results[$k] = $this->transmogrify($v, $context);
            }

            return $results;
        }

        $value = $this->parseValue($value, $context);

        if ($this->isPresentable($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            sprintf(
                'The context must transmogrify to a scalar or array value (of likewise values); received "%s".',
                get_var_type($value)
            )
        );
    }

    /**
     * Retrieve a value from an array or object using "dot" notation.
     *
     * Note: Adapted from `data_get()` and `object_get()` of
     * {@link https://github.com/illuminate/support/blob/master/helpers.php Illuminate}.
     *
     * @uses   AbstractPresenter::filterTarget()
     * @param  mixed  $target  The dataset to access.
     * @param  string $key     The key to retrieve.
     * @return mixed Returns the key's value, or NULL.
     */
    private function dataGet($target, $key)
    {
        if (is_null($key) || trim($key) == '') {
            return $target;
        }

        if (false !== strpos($key, self::ACCESSOR_WILDCARD)) {
            $key = is_array($key) ? $key : explode(self::ACCESSOR_SEPARATOR, $key);

            while (!is_null($segment = array_shift($key))) {
                if ($segment === self::ACCESSOR_WILDCARD) {
                    if ($target instanceof Traversable) {
                        $target = $this->convertToArray($target);
                    } elseif (!is_array($target)) {
                        return null;
                    }

                    $result = Arr::pluck($target, $key);

                    return in_array(self::ACCESSOR_WILDCARD, $key) ? Arr::collapse($result) : $result;
                }

                if (!$this->filterTarget($target, $segment)) {
                    return null;
                }
            }
        } else {
            foreach (explode(self::ACCESSOR_SEPARATOR, $key) as $segment) {
                if (!$this->filterTarget($target, $segment)) {
                    return null;
                }
            }
        }

        return $target;
    }

    /**
     * Retrieve a value from an array or object.
     *
     * @used-by AbstractPresenter::dataGet()
     * @param   mixed  $target  The dataset to access, passed by reference.
     * @param   string $key     The key to retrieve.
     * @return  boolean Returns TRUE if a value was retrieved, FALSE otherwise.
     */
    protected function filterTarget(&$target, $key)
    {
        if (Arr::accessible($target) && Arr::exists($target, $key)) {
            $target = $target[$key];
            return true;
        } elseif (is_object($target) && isset($target->{$key})) {
            $target = $target->{$key};
            return true;
        } elseif (is_callable([ $target, $key ])) {
            $target = $target->{$key}();
            return true;
        } else {
            $target = null;
            return false;
        }
    }

    /**
     * Filter, by reference, the given key/value pair.
     *
     * @used-by AbstractPresenter::transmogrify()
     * @param   mixed $key     A key to filter.
     * @param   mixed $val     A value to filter.
     * @param   mixed $context Array or object transforming context.
     * @return  boolean Returns TRUE on success or FALSE on failure.
     */
    protected function filterPair(&$key, &$val, $context = null)
    {
        if (!is_string($key) && is_string($val)) {
            if (
                $val[0] === self::PRESENTER_ACCESSOR ||
                $val[0] === self::CONTEXT_ACCESSOR
            ) {
                $key = explode(self::SEPARATOR, $val);
                $key = substr(reset($key), 1);

                return true;
            }
        }

        return false;
    }

    /**
     * Parses a given value.
     *
     * @param  mixed $value   A value to resolve.
     * @param  mixed $context Array or object transforming context.
     * @return mixed Returns the parsed the value.
     */
    protected function parseValue($value, $context = null)
    {
        if (is_null($default)) {
            return null;
        }

        if ($value instanceof Closure) {
            $value = $value($context);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $value = strval($value);
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format(self::DATETIME_FORMAT);
        }

        return $value;
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed $value The value to check.
     * @return boolean Returns TRUE if var is callable, FALSE otherwise.
     */
    protected function useAsCallable($value)
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * Determine whether the given value is array-presentable.
     *
     * @param  mixed $value The variable being evaluated.
     * @return boolean Returns TRUE if var is presentable, FALSE otherwise.
     */
    protected function isPresentable($value)
    {
        if (is_scalar($value)) {
            return true;
        }

        if (is_null($value)) {
            return true;
        }

        if (is_array($value)) {
            return !!array_filter($value, [ $this, 'isPresentable' ]);
        }

        return false;
    }

    /**
     * Retrieve the collection as a plain array.
     *
     * @param  mixed $var The variable that is being converted to an array.
     * @return mixed Returns the array value of $var.
     */
    protected function convertToArray($var)
    {
        if (is_array($var)) {
            return $var;
        } elseif ($var instanceof IlluminateCollection) {
            return $var->all();
        } elseif ($var instanceof CharcoalCollection) {
            return $var->all();
        } elseif ($var instanceof CharcoalConfig) {
            return $var->data();
        } elseif (method_exists($var, 'toArray')) {
            return $var->toArray();
        } elseif (method_exists($var, 'toJson')) {
            return json_decode($var->toJson(), true);
        } elseif ($var instanceof JsonSerializable) {
            return $var->jsonSerialize();
        } elseif ($var instanceof Traversable) {
            return iterator_to_array($var);
        }

        return (array)$var;
    }
}
