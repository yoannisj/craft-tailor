<?php
namespace yoannisj\tailor\models;

use yii\base\InvalidArgumentException;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\base\EagerLoadingFieldInterface;
use craft\elements\MatrixBlock;
use craft\elements\db\EagerLoadPlan;
use craft\errors\InvalidFieldException;
use verbb\supertable\elements\SuperTableBlockElement as SuperTableBlock;

use yoannisj\tailor\helpers\ContentHelper;

/**
 * Class encapsulating attribute inspectio plans
 */
class AttributeAccessPlan extends EagerLoadPlan
{
    // =Properties
    // =========================================================================

    /**
     * @var bool Whether the attribute should be excluded during inspection
     */
    public bool $exclude = false;

    /**
     * @var string|null Optional owner type included in handle
     */
    public ?string $typeHandle;

    /**
     * @var ?string Attribute inspected by this plan
     */
    public string $attribute;

    /**
     * Checks whether attribute is eager-loadable for given elements
     *
     * @param string|ElementInterface $elementType Root element type
     * @param array $sourcElements Elements for which to eager-load data
     *
     * @return bool
     */
    public function eagerLoadable(
        string|ElementInterface $elementType,
        array $sourceElements
    ): bool
    {
        if ($this->exclude || empty($sourceElements)) return false;

        // check if field exists and supports eager loading
        $field = null;

        if ($this->typeHandle)
        {
            // we also need elements matching the type
            $foundType = false;
            foreach ($sourceElements as $element)
            {
                $typeHandle = ContentHelper::elementPlanTypeHandle($element);
                if ($typeHandle === $this->typeHandle)
                {
                    $foundType = true;

                    // for elements with a contextual field layout
                    if ($element instanceof MatrixBlock
                        || $element instanceof SuperTableBlock)
                    {
                        try {
                            $field = $element->getFieldLayout()->getField($this->attribute);
                        } catch (InvalidArgumentException|InvalidFieldException) {
                            // that's Ok: field just doesn't exist in this context
                        }
                    }

                    break;
                }
            }

            // no elements matching this type? no eager loading!
            if (!$foundType) return false;
        }

        if (!$field) { // try field in global context
            $field = Craft::$app->getFields()->getFieldByHandle($this->attribute);
        }

        if ($field) {
            return ($field instanceof EagerLoadingFieldInterface);
        }

        // assume this is an eager-loadable attribute
        return true;
    }
}