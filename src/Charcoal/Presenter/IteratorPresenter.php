<?php

namespace Charcoal\Presenter;

use \InvalidArgumentException;
use \IteratorAggregate;
use \ArrayIterator;
use \Traversable;

/**
 * This iterator wrapper allows an associative array to be iterable within Mustache.
 *
 * > In most languages there are two distinct array types: _list_ and _hash_ (or whatever
 * > you want to call them). Lists should be iterated, hashes should be treated as objects.
 * > Mustache follows this paradigm for Ruby, Javascript, Java, Python, etc.
 *
 * > PHP, however, treats lists and hashes as one primitive type: _array_. So Mustache.php needs
 * > a way to distinguish between between a list of things (numeric, normalized array) and a set
 * > of variables to be used as section context (associative array).
 * — {@see \Mustache_Template::isIterable()}
 *
 * @link https://gist.github.com/bobthecow/61161639d8be82a75b5e#file-iteratorpresenter-php
 *     Based on {@author bobthecow}'s original iterator.
 */
class IteratorPresenter implements IteratorAggregate
{
    /**
     * @const Default iterator classname to be used by IteratorPresenter::getIterator().
     */
    const STD_ITERATOR = 'ArrayIterator';

    /**
     * @var array|Traversable The (associative) array or object to be iterated on.
     */
    private $values;

    /**
     * @var callable|null Callback function to run for each element by IteratorPresenter::getIterator().
     */
    private $callback;

    /**
     * @var string Iterator classname used by IteratorPresenter::getIterator().
     */
    private $iteratorClass;

    /**
     * Construct an IteratorPresenter
     *
     * @param  array|Traversable $values        The (associative) array or object to be iterated on.
     * @param  callable|null     $callback      Optional callback function to run for each element in $values.
     * @param  string|null       $iteratorClass Optional classname that will be used for iteration of the $values.
     * @throws InvalidArgumentException If the values aren't an array or Traversable object.
     */
    public function __construct($values, callable $callback = null, $iteratorClass = self::STD_ITERATOR)
    {
        if ( ! is_array($values) && ! $values instanceof Traversable ) {
            throw new InvalidArgumentException(
                sprintf('%s requires an array or Traversable object', __CLASS__)
            );
        }

        $this->values = $values;

        if ( isset($callback) ) {
            $this->setCallback($callback);
        }

        if ( isset($iteratorClass) ) {
            $this->setIteratorClass($iteratorClass);
        }
    }

    /**
     * Gets the callback function by IteratorPresenter::getIterator().
     *
     * @return callable Returns the callback function that is used for each iteration.
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * Sets the callback function by IteratorPresenter::getIterator().
     *
     * @param  callable $callback The classname of the array iterator to use when iterating over this object.
     * @return self
     */
    public function setCallback(callable $callback)
    {
         $this->callback = $callback;

        return $this;
    }

    /**
     * Gets the iterator classname for the IteratorPresenter.
     *
     * Gets the class name of the array iterator that is used by IteratorPresenter::getIterator().
     *
     * @return Traversable Returns the iterator class name that is used to iterate over this object.
     */
    public function getIteratorClass()
    {
        return $this->iteratorClass;
    }

    /**
     * Sets the iterator classname for the IteratorPresenter.
     *
     * Sets the classname of the array iterator that is used by IteratorPresenter::getIterator().
     *
     * @param  string $iteratorClass The classname of the array iterator to use
     *                                when iterating over this object.
     * @return self
     * @throws InvalidArgumentException If the iterator isn't valid.
     */
    public function setIteratorClass($iteratorClass)
    {
        if ( !is_string($iteratorClass) ) {
            throw new InvalidArgumentException(
                'External iterator class must be a string.'
            );
        }

        if ( class_exists($iteratorClass) ) {
            $this->iteratorClass = $iteratorClass;
        } else {
            $this->iteratorClass = self::STD_ITERATOR;

            throw new InvalidArgumentException(
                sprintf(
                    'Class "%s" must exist and be an implementation of Iterator or Traversable.',
                    $iteratorClass
                )
            );
        }

        return $this;
    }

    /**
     * Create a new iterator from an IteratorPresenter instance
     *
     * @return Traversable An iterator from an IteratorPresenter.
     */
    public function getIterator()
    {
        $values = [];

        foreach ( $this->values as $key => $val ) {
            $item = [
                'key'     => $key,
                'value'   => $val,
                'isFirst' => false,
                'isLast'  => false,
            ];

            if ( is_callable($this->callback) ) {
                /**
                 * Apply a user supplied function to every member of the iterator
                 *
                 * @param mixed             &$item   The value of the current iteration.
                 * @param string|int         $key    The key of the current iteration.
                 * @param array|Traversable  $values The array or object $item belongs to.
                 */
                call_user_func_array($this->callback, [ &$item, $key, $this->values ]);
            }

            $values[$key] = $item;
        }

        $keys = array_keys($values);

        if ( ! empty($keys) ) {
            $values[reset($keys)]['isFirst'] = true;
            $values[end($keys)]['isLast']    = true;
        }

        return new $this->iteratorClass($values);
    }
}
