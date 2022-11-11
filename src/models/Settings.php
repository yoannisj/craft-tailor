<?php
namespace yoannisj\tailor\models;

use craft\base\Model;
use craft\helpers\ConfigHelper;

use yoannisj\tailor\helpers\ContentHelper;

/**
 * Model encapsulating the Tailor plugin's config settings.
 */
class Settings extends Model
{
    // =Static
    // =========================================================================

    // =Properties
    // =========================================================================

    /**
     * List of description field handles for Craft elements.
     * 
     * If set to `null`, all text fields will be considered when extracting
     * an element's description from its content.
     * 
     * This uses the same syntax as ElementQuery's `with` parameter for eager loading,
     * but you can specify non-relational fields (e.g. text fields) and prepend a
     * field handle with the `!` character to exclude it. 
     * 
     * Note that excluding a field higher up a nested field chain will ignore any
     * nested fields. In the following example, the matrix block's `label` field
     * will be ignored.
     * 
     * ```
     * [
     *  'entriesField.matrixField.blockType:label,
     *  'entriesField.!matrixField,
     * ]
     * ```
     * 
     * This can always be overriden by the `$options` argument when calling
     * `ContentHelper::elementDescription()`.
     * 
     * @var ?string[]|craft\elements\db\EagerLoadPlan[]
     * 
     * @used-by ContentHelper::elementDescription()
     */
    public ?array $descriptionFields;

    /**
     * Default characters limit on the length of element descriptions.
     * 
     * Set this to a `false` if element descriptions should not be truncated.
     * This can always be overriden by the `$options` argument when calling
     * `ContentHelper::elementDescription()`.
     * 
     * @var int|bool
     * 
     * @used-by ContentHelper::elementDescription()
     */
    public int|bool $maxDescriptionLength = 155;

    /**
     * List of preparse field handles that store text content instead of data.
     * 
     * If set to `null`, only preparse fields using the _text column type_
     * and _allowing selection_ will be considered text fields.
     *
     * @var array|null
     */
    public ?array $textPreparseFields = null;

    // =Public Methods
    // =========================================================================

    // =Protected Methods
    // =========================================================================
}