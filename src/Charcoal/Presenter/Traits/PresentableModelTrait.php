<?php

namespace Charcoal\Presenter\Traits;

// From 'charcoal-presenter'
use Charcoal\Object\PresentableInterface;

/**
 * Provides support for describable Charcoal models.
 *
 * To be used by instanceof {@see \Charcoal\Presenter\PresenterInterface}.
 */
trait PresentableModelTrait
{
    /**
     * {@inheritdoc}
     */
    protected function filterPair(&$key, &$val, $context = null)
    {
        if (!is_string($key) && is_string($val)) {
            if (is_object($context) && $context instanceof PresentableInterface) {
                $metadata = $context->metadata();
                if (isset($metadata['presenters']['aliases'][$val])) {
                    $key = $val;
                    $val = $metadata['presenters']['aliases'][$key];
                    return true;
                }
            }
        }

        return parent::filterPair($key, $val, $context);
    }
}
