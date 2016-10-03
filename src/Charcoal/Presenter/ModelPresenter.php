<?php

namespace Charcoal\Presenter;

use InvalidArgumentException;
use ArrayAccess;
use Traversable;

use Charcoal\Model\DescribableInterface;
use Charcoal\Model\ModelInterface;

/**
 * Presenter for describable models
 */
class ModelPresenter extends Presenter
{
    /**
     * Transform the given object.
     *
     * Its purpose is to transform a model (object) into view-data.
     *
     * The transformer is set from the constructor.
     *
     * @param  ModelInterface $obj The original data (object / model) to transform into view-data.
     * @return array Normalized data, suitable as presentation (view) layer
     */
    public function transform(ModelInterface $obj)
    {
        return $this->transmogrify($obj, $this->transformer($obj));
    }

    /**
     * {@inheritdoc}
     */
    protected function objectGet($obj, $attr)
    {
        if ($obj instanceof DescribableInterface) {
            $metadata = $obj->metadata();

            if (isset($metadata['presenters']['aliases'][$attr])) {
                return $this->transmogrify($obj, $metadata['presenters']['aliases'][$attr]);
            }
        }

        return parent::objectGet($obj, $attr);
    }
}
