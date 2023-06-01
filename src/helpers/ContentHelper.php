<?php
namespace yoannisj\tailor\helpers;

use Stringy\Stringy;

use Illuminate\Support\Collection;

use yii\base\InvalidArgumentException;
use yii\base\UnknownPropertyException;
use yii\base\Model as YiiModel;
use yii\db\Query;

use Craft;
use craft\base\FieldInterface;
use craft\base\ElementInterface;
use craft\elements\db\EagerLoadPlan;
use craft\elements\MatrixBlock;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\ArrayHelper;
use craft\helpers\HtmlPurifier;

use benf\neo\elements\Block as NeoBlock;
use craft\helpers\StringHelper;
use GraphQL\Exception\InvalidArgument;
use verbb\supertable\elements\SuperTableBlockElement as SuperTableBlock;
use verbb\vizy\fields\VizyField;
use verbb\vizy\models\NodeCollection as VizyNodeCollection;
use verbb\vizy\nodes\VizyBlock;

use yoannisj\tailor\Tailor;
use yoannisj\tailor\models\AttributeAccessPlan;
use yoannisj\tailor\helpers\FieldsHelper;
use yoannisj\tailor\helpers\DataHelper;

/**
 * Class implementing helper methods to work with Craft elements’ content
 */
class ContentHelper
{
    // =Properties
    // =========================================================================

    const TEXT_BUILD_MODE_APPEND = 'append';
    const TEXT_BUILD_MODE_LIST = 'map';
    const TEXT_BUILD_MODE_MAP = 'map';

    // =Public Methods
    // =========================================================================

    /**
     * Applies given options to text, and collects additional information
     * about the text.
     * 
     * @param string $text Text to process
     * @param array $options Options to apply to text
     * @param ?array &info Info collected while processing text
     */
    public static function processText(
        string $text,
        array $options = [],
        ?array &$info = null
    ): string
    {
        $text = static::sanitizeText($text, $options);

        $transform = $options['transform'] ?? null;
        if (is_callable($transform)) {
            // $text = $transform($text, $model, $attribute);
            $text = $transform($text);
        }

        $length = mb_strlen($text);
        $reachedLimit = false;

        $limit = $options['limit'] ?? null;
        $hasLimit = ($limit && $limit > 0);

        if ($hasLimit)
        {
            if ($length > $limit)
            {
                $suffix = $options['suffix'] ?? "…";
                $preserveWords = $options['preserveWords'] ?? true;

                $text = static::truncateText(
                    $text, $limit, $suffix, $preserveWords);
                
                if ($preserveWords) $length = mb_strlen($text);
                $reachedLimit = true;
            } else {
                $reachedLimit = ($length == $limit);
            }
        }

        $info = [
            'length' => $length,
            'reachedLimit' => $reachedLimit,
        ];

        return $text;
    }

    /**
     * Applies sanitization options to given text string
     *
     * @param string $text 
     * @param array $options 
     *
     * @return string
     */
    public static function sanitizeText(
        string $text,
        array $options = []
    ): string
    {
        $allowHtml = $options['allowHtml'] ?? null;
        if ($allowHtml !== true)
        {
            // add a space behind each closing HTML tag to avoid words being
            // glued together when we remove the html tags.
            $text = preg_replace('/(<\/[a-zA-Z\-_]+>)/', '$1 ', $text);
            $text = strip_tags($text, $allowHtml);
            $text = html_entity_decode($text, ENT_NOQUOTES);
            $text = trim(preg_replace('/ +/', ' ', $text)); // remove double spaces
        }

        else
        {
            $htmlPurifierOptions = $options['htmlPurifier'] ?? true;
            if ($htmlPurifierOptions === true) {
                $htmlPurifierOptions = ['HTML.Allowed' => ''];
            }

            if ($htmlPurifierOptions) {
                $text = HtmlPurifier::process($text, $htmlPurifierOptions);
            }
        }

        return $text;
    }

    // =Plans
    // -------------------------------------------------------------------------

    /**
     * Returns eager-laoding plan handle for given model attribute.
     *
     * @param YiiModel $model Model the attribute belongs to
     * @param FieldInterface $field Field to get the handle for
     *
     * @return string
     */
    public static function attributePlanHandle(
        YiiModel $model,
        string $attribute
    ): string
    {
        if ($model instanceof ElementInterface) {
            $typeHandle = static::elementPlanTypeHandle($model);
        } else if ($model instanceof VizyBlock) {
            $typeHandle = $model->handle;
        }

        if ($typeHandle) {
            return $typeHandle.':'.$attribute;
        }

        return $attribute;
    }

    /**
     * Returns the planType for given element
     *
     * @param ElementInterface $element 
     *
     * @return string|null
     */
    public static function elementPlanTypeHandle(
        ElementInterface $element
    ): ?string
    {
        if ($element instanceof MatrixBlock || $element instanceof NeoBlock) {
            return $element->getType()->handle;
        }

        if ($element instanceof SuperTableBlock) {
            return explode('_', $element->getType()->handle)[0];
        }

        return null;
    }

    /**
     * Parses given attribute access plans into list of normalized
     * [[AttributeAccessPlan]] models. Returns `null` (meaning all attributes
     * should be accessed), if given plans argument is `null` or `*`.
     *
     * @param string|EagerLoadPlan[]|AttributeAccessPlan[]|null $plans 
     *
     * @return AttributeAccessPlan[]|null
     */
    public static function createAttributeAccessPlans( string|array $plans = null ): ?array
    {
        if ($plans === null || $plans === '*') {
            return null;
        }

        $plans = Craft::$app->getElements()->createEagerLoadingPlans($plans);

        return static::_createAttributeAccessPlans($plans);
    }

    /**
     * Filters list of plans to return only those that apply to given eager
     * loading parameters/context.
     *
     * @param string|ElementInterface $elementType Root element type
     * @param ElementInterface[] $sourceElements Elements for which to eager-load data
     * @param AttributeAccessPlan[]|EagerLoadPlan[] $plans Eager loading or attribute access plans
     *
     * @return AttributeAccessPlan[]
     */
    public static function selectEagerLoadingPlans(
        string|ElementInterface $elementType,
        array $sourceElements,
        string|array $plans
    ): array
    {
        if (is_string($plans)) {
            $plans = StringHelper::split($plans);
        }

        if (empty($plans)) return [];

        $eagerLoadingPlans = [];

        foreach ($plans as $plan)
        {
            /** @var EagerLoadPlan $plan */
            if (!($plan instanceof AttributeAccessPlan)) {
                $plan = static::_createAttributeAccessPlan($plan);
            }

            if (!$plan->eagerLoadable($elementType, $sourceElements)){
                continue; // don't include plan which is not eager-loadable
            }

            // no need to selected nested plans quite yet, as those will be
            // selected when they are applied to plan's eager-loaded elements

            $eagerLoadingPlans[] = $plan;
        }

        return $eagerLoadingPlans;
    }

    /**
     * Collects all nested plans in given set of eager loading plans.
     *
     * @param EagerLoadPlan[] $plans Plans to collect from
     *
     * @return EagerLoadPlan[]
     */
    public static function allNestedPlans( array $plans ): array
    {
        $nestedPlans = [];

        foreach ($plans as $plan)
        {
            $nestedPlans = array_merge(
                $nestedPlans, $plan->nested ?: []);
        }

        return $nestedPlans;
    }

    // =Text
    // -------------------------------------------------------------------------

    /**
     * Truncates text o given max-chars limit, including an optional suffix
     * and/or preserving the last words in the text.
     *
     * @param string $text 
     * @param int $limit 
     * @param ?string $suffix 
     * @param ?bool $preserveWords 
     *
     * @return string
     */
    public static function truncateText(
        string $text,
        int $limit,
        string $suffix = "…",
        bool $preserveWords = false,
    ): string
    {
        $stringy = Stringy::create($text);

        return (string)($preserveWords ?
            $stringy->safeTruncate($limit, $suffix) :
            $stringy->truncate($limit, $suffix));
    }

    /**
     * Appends given text with support for glue substring, max characters limit
     * and text truncation options.
     * 
     * The `options` argument, supports the following keys:
     * - 'glue' (string) Substring inserted between base text and appended text
     * - 'limit' (int) Max characters limit (truncates resulting text when reached)
     * - 'suffix' (string) Suffix added when truncating resulting text
     * - 'preserveWords' (bool) Whether to preserve the last word when truncating
     * 
     * You can pass in the `$reachedLimit` argument to find out if the resulting
     * text has reached the limit defined in `$options` (and was truncated subsequently).
     * 
     * @param string $text Base text to append to
     * @param string $append Appended text
     * @param array $options Concatenation/truncation options
     * @param ?bool &$reachedLimit Indicates whether the resulting text has been truncated
     */
    public static function appendText(
        string $text,
        string $append,
        array $options = [], 
        array &$info = null
    ): string
    {
        $glue = $options['glue'] ?? "\n\n";
        $limit = $options['limit'] ?? null;

        $text = $text ? $text.$glue.$append : $append;

        $length = mb_strlen($text);
        $reachedLimit = false;

        if ($limit !== null)
        {
            if ($length > $limit)
            {
                $suffix = $options['suffix'] ?? "…";
                $preserveWords = $options['preserveWords'] ?? false;

                $text = static::truncateText(
                    $text, $limit, $suffix, $preserveWords);

                    $reachedLimit = true;
                    if ($preserveWords) $length = mb_strlen($text);
            }

            else {
                $reachedLimit = ($length == $limit);
            }
        }

        $info = [
            'length' => $length,
            'reachedLimit' => $reachedLimit,
        ];

        return $text;
    }

    /**
     * Checks whether given value is an object that can be cast to a string
     *
     * @param mixed $value 
     *
     * @return bool
     */
    public static function isStringableObject( $value ): bool
    {
        return (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * Returns list of attributes storing text for given model
     *
     * @param YiiModel $model 
     *
     * @return array
     * 
     * @todo: feat(defaultTextAttrs) include (some) native fields
     * @todo: feat(defaultTextAttrs) trigger event to define element's default text attributes
     * @todo: feat(defaultTextAttrs) trigger event to define model's default text attributes
     */
    public static function defaultTextAttributes( YiiModel $model ): array
    {
        $attrs = [];

        if ($model instanceof ElementInterface) {
            $attrs[] = 'title';
        }

        // @todo include (some) native fields as well?
        $fieldLayout = null;
        
        try { $fieldLayout = $model->fieldLayout; }
        catch (UnknownPropertyException) {} // whatever

        if ($fieldLayout)
        {
            /** @var CustomField[] $fields */
            $fields = $fieldLayout->getCustomFieldelements();

            foreach ($fields as $field) {
                $attrs[] = $field->attribute();
            }
        }

        return $attrs;
    }

    /**
     * Extracts text from given element's content
     * 
     * @param YiiModel $element
     * @param ?array $inAttributes
     * @param array $options
     * 
     * @return string
     * 
     * @todo: fix(extractText) `limit` option is not respected (result is shorter)
     * @todo: fix(extractText) `preserverWords` option is not working
     */
    public static function textFromContent(
        YiiModel $model,
        string|array|null $inAttributes = [],
        array $options = [],
        array $info = null
    ): string|array
    {
        $plans = null;

        if ($inAttributes !== null)
        {
            // explicitly no fields => no text
            if (empty($inAttributes)) return '';

            $plans = static::createAttributeAccessPlans($inAttributes);

            if ($plans && ($model instanceof ElementInterface)
                && ($options['eagerLoad'] ?? true))
            {
                // non eager loading plans will be discarded via internal event
                Craft::$app->getElements()->eagerLoadElements(
                    $model::class, [ $model ], $plans);
            }
        }

        $plans = static::_sortAttributeAccessPlans($plans);
        $text = static::_textFromContent($model, $plans, $options);

        $asMap = $options['asMap'] ?? false;

        if ($asMap) {
            return static::_mapText($text, $options, $info);
        }

        return static::_flattenText($text, $options, $info);
    }

    /**
     * Returns text from given collection of Vizy Nodes
     *
     * @param VizyNodeCollection $nodes 
     * @param array $plans 
     * @param array $options 
     *
     * @return string
     */
    public static function textFromVizyNodeCollection(
        VizyNodeCollection $collection,
        array $plans = null,
        array $options = []
    ): string|array
    {
        $text = [];
        $plans = static::_sortAttributeAccessPlans($plans); 

        foreach ($collection->getNodes() as $node)
        {
            $nodeText = null;

            if ($node->type == 'vizyBlock')
            {
                $nodeOptions = array_merge([], $options);
                $nodeText = static::_textFromContent($node, $plans, $nodeOptions);
            }

            else {
                $nodeText = static::sanitizeText($node->renderNode(), $options);
            }

            if ($nodeText) $text[] = $nodeText;
        }

        return $text;
    }

    /**
     * Returns text value from given model attribute
     *
     * @param YiiModel $model Model to inspect
     * @param string $attribute Attribute to get text from
     * @param array $options Options for text to return
     *
     * @return string|array
     * 
     * @throws UnkownPropertyException If given model attribute does not exist
     */
    public static function textFromAttribute(
        YiiModel $model,
        string $attribute,
        array $plans = null,
        array $options = [],
        array &$info = null
    ): string|array|null
    {
        $plans = static::_sortAttributeAccessPlans($plans);
        $text = static::_textFromAttribute($model, $attribute, $plans, $options);

        if (!$text) return null;

        $flatten = $options['flatten'] ?? true;

        if (is_array($text) && $flatten) {
            return static::_flattenText($text, $options, $info);
        }

        if (is_array($text)) {
            return static::_mapText($text, $options, $info);
        }

        return static::processText($text, $options, $info);
    }

    /**
     * Returns description text for given Craft element.
     *
     * @param ElementInterface $element 
     * @param string|array|null $inAttributes 
     * @param  $options 
     *
     * @return string
     */
    public static function elementDescription(
        ElementInterface $element,
        string|array|null $inAttributes = null,
        array $options = [],
    ): string
    {
        $tailorSettings = Tailor::$plugin->getSettings();

        if ($inAttributes === null) {
            $inAttributes = $tailorSettings->descriptionFields;
        }

        if (!array_key_exists('limit', $options)) {
            $options['limit'] = $tailorSettings->maxDescriptionLength;
        }

        return static::textFromContent($element, $inAttributes, $options);
    }

    // =Private Methods
    // =========================================================================

    /**
     * Normalises given attribute access plans into list of AttributeAccesPlan
     * models, and removes conflicting plans (i.e. plans which are excluded by
     * other plans in the list).
     *
     * @param EagerLoadPlan[] $plans 
     *
     * @return AttributeAccessPlan[]
     */
    private static function _createAttributeAccessPlans( array $plans ): array
    {
        $attrPlans = [];
        $excludedPlans = [];

        foreach ($plans as $plan)
        {
            if (in_array($plan->handle, $excludedPlans)) {
                continue; // plan shouldn't be here
            }

            if ($plan instanceof AttributeAccessPlan) {
                $attrPlan = $plan; // already parsed ;)
            } else {
                $attrPlan = static::_createAttributeAccessPlan($plan);
            }

            if ($attrPlan->exclude) {
                // remove other plans which are invalidated by this one
                $excludedPlanHandle = substr($plan->handle, 1);
                $excludedPlans[$excludedPlanHandle] = true;
            }

            $attrPlans[] = $attrPlan;
        }

        // remove any plans which were listed before another plan excluded them
        $excludedPlans = array_keys($excludedPlans);
        if ($excludedPlans)
        {
            foreach ($attrPlans as $key => $attrPlan)
            {
                if (in_array($attrPlan->handle, $excludedPlans)) {
                    unset($attrPlans[$key]);
                }
            }
        }

        return array_values($attrPlans);
    }

    /**
     * Creates an attribute access plan base on given eager loading plan.
     *
     * @param EagerLoadPlan $plan Eager loading plan to parse
     * @param string $elementType The root element type class
     * @param ElementInterface[] $sourceElements The element models on which to eager-load the data
     * @param FieldInterface|null $parentField The field of the parent plan (if any)
     *
     * @return AttributeAccessPlan
     */
    private static function _createAttributeAccessPlan(
        EagerLoadPlan $plan
    ): AttributeAccessPlan
    {
        $handleParts = explode(':', $plan->handle);

        if (count($handleParts) > 1) {
            $typeHandle = $handleParts[0];
            $attribute = $handleParts[1];
        } else {
            $typeHandle = null;
            $attribute = $handleParts[0];
        }

        $exclude = (substr($plan->handle, 0, 1) == '!');

        if (!$exclude && $plan->nested) {
            $nestedPlans = static::_createAttributeAccessPlans($plan->nested);
        } else {
            $nestedPlans = [];
        }

        return new AttributeAccessPlan([
            'handle' => $plan->handle,
            'alias' => $plan->alias,
            'exclude' => $exclude,
            'typeHandle' => $typeHandle,
            'attribute' => $attribute,
            'criteria' => $plan->criteria,
            'all' => $plan->all,
            'count' => $plan->count,
            'when' => $plan->when,
            'nested' => $nestedPlans,
        ]);
    }

    /**
     * Analyzes given set of attribute access plans, differenties the
     * including plans from the excluding plans, and collects additional
     * information to inspect an element's content.
     *
     * Returns an array with the following keys:
     * @var AttributeAccessPlan[]|null `include` List of inclusion plans
     * @var AttributeAccessPlan[]|null `exclude` List of exclusion plans
     * @var bool `hasIncludes` Whether there are any inclusion plans
     * @var bool `hasExcludes` Whether there are any exclusion plans
     * @var bool `inclusionsOnly` Whether there are only inclusion plans
     * @var bool `exclusionsOnly` Whether there are only exclusion plans
     * 
     * @param mixed $plans 
     *
     * @return array
     */
    private static function _sortAttributeAccessPlans( array $plans = null ): array
    {
        $sorted = [
            'include' => null,
            'exclude' => null,
            'hasIncludes' => false,
            'hasExcludes'=> false,
            'includeAll' => true,
        ];

        if ($plans == null) {
            return $sorted;
        }

        $sorted['includeAll'] = false;

        foreach ($plans as $plan)
        {
            if ($plan->exclude)
            {
                if (!$sorted['hasExcludes']) {
                    $sorted['exclude'] = [];
                    $sorted['hasExcludes'] = true;
                }

                $sorted['exclude'][] = $plan;
            }

            else
            {
                if (!$sorted['hasIncludes']) {
                    $sorted['include'] = [];
                    $sorted['hasIncludes'] = true;
                }

                $sorted['include'][] = $plan;
            }
        }

        return $sorted;
    }

    /**
     * Checks whether given elmenet attribute is included in plans, and returns
     * the plans which include it if any.
     * 
     * Returns `true` if attribute is included but has no specific plans.
     * Returns `false` if attribute is excluded or not included.
     * Returns attribute specific inclusion plans otherwise.
     *
     * @param ElementInterface $element Element to inspect
     * @param string $attribute Attribute to inspect
     * @param array $sortedPlans Sorted attribute access plans for $element
     *
     * @return bool|array
     */
    private static function _attributePlans(
        YiiModel $model,
        string $attribute,
        array $sortedPlans
    ): bool|array
    {
        $attrPlans = true;

        if (!$sortedPlans['includeAll'])
        {
            $handle = static::attributePlanHandle($model, $attribute);

            if ($sortedPlans['hasExcludes'])
            {
                $isExcluded = ArrayHelper::firstWhere(
                    $sortedPlans['exclude'], 'handle', '!'.$handle);
            
                // if this field was specifically excluded, ignore it!
                if ($isExcluded) return false;
            }

            if ($sortedPlans['hasIncludes'])
            {
                $attrPlans = ArrayHelper::where(
                    $sortedPlans['include'], 'handle', $handle);

                // if this field was not explicitly included, ignore it!
                if (!$attrPlans) return false;
            }
        }

        return $attrPlans;
    }

    /**
     * Returns text data stored in given model's attribute
     *
     * @param YiiModel $model 
     * @param string $attribute 
     * @param array|null $plans 
     * @param array $options 
     *
     * @return string|array|null
     */
    private static function _textFromAttribute(
        YiiModel $model,
        string $attribute,
        array $plans = null,
        array $options = []
    ): string|array|null
    {
        $value = null;

        // check if attribute points to a field
        $field = null;
        try { $field = $model->fieldLayout->getFieldByHandle($attribute); }
        catch (UnknownPropertyException|InvalidArgumentException $e) {}

        // extract text from regular attribute
        if (!$field)
        {
            $value = $model->$attribute; // this can throw an error ;)

            if ($value instanceof Query || $value instanceof Collection)
            {
                $value = static::_textFromRelatedContent(
                    $model, $attribute, $plans, $options);
            }

            else if (static::isStringableObject($value)) {
                $value = (string)$value;
            }
        }

        else if ($field instanceof VizyField)
        {
            $nodes = $model->getFieldValue($attribute);
            $value = static::textFromVizyNodeCollection(
                $nodes, $plans, $options);
        }

        // extract text from text field
        else if (FieldsHelper::isPlainTextField($field)
            || FieldsHelper::isMultiLineTextField($field)
            || FieldsHelper::isComputedTextField($field)
            || FieldsHelper::isRichTextField($field))
        {
            $value = (string)$model->getFieldValue($field->handle);
        }

        // extract text from relations field
        else if (FieldsHelper::isRelationsField($field)
            || FieldsHelper::isBlocksField($field)) // not all block fields are relational
        {
            $value = static::_textFromRelatedContent(
                $model, $field->handle, $plans, $options);
        }

        if ($value && is_string($value)) {
            $value = static::sanitizeText($value, $options);
        }

        return $value ?: null;
    }

    /**
     * Builds text based on 
     *
     * @param ElementInterface $element 
     * @param array|null $plans 
     * @param array $options 
     *
     * @return array
     * 
     * @todo feat(extractText): stop once text limit is reached (use a `AccumulativeText` object to keep track of text length and limit while populating the text array)
     * @todo feat(articleField): support extracting text from Article fields
     * @todo feat(doxterField): support extracting text from Doxter fields
     * @todo feat(doxterField): support extracting text from Ether’s SEO fields
     * @todo feat(doxterField): support extracting text from Studio Espresso’s SEO fields
     * @todo feat(doxterField): support extracting text from SEOmatic’s SEO Settings fields
    **/
    private static function _textFromContent(
        YiiModel $model,
        array $plans,
        array $options = [],
    ): array
    {
        $attributes = [];

        // determine attributes to inspect
        if ($plans['includeAll']) {
            $attributes = static::defaultTextAttributes($model);
        } else if ($plans['hasIncludes']) {
            $attributes = ArrayHelper::getColumn($plans['include'], 'attribute');
        }

        // no inspectable attributes? no text!
        if (!$attributes) return [];

        $text = [];

        foreach ($attributes as $attribute)
        {
            $attrPlans = static::_attributePlans($model, $attribute, $plans);
            if ($attrPlans === false) continue;

            // include all sub-attributes by default
            if ($attrPlans === true) $attrPlans = null;

            $attrText = static::_textFromAttribute(
                $model, $attribute, $attrPlans, $options);

            if ($attrText)
            {
                $text[$attribute] = $attrText;
            }
        }

        return $text;
    }

    /**
     * Returns text data stored in given source element's relational attribute
     *
     * @param ElementInterface $source 
     * @param string $attribute 
     * @param ?array $plans 
     * @param ?array $options 
     *
     * @return string|array
     */
    private static function _textFromRelatedContent(
        YiiModel $source,
        string $attribute,
        array $plans = null,
        array $options = []
    ): array
    {
        $text = [];

        $relatedItems = DataHelper::fetchAll($source->$attribute);
        if (empty($relatedItems)) return $text;

        $itemPlans = static::_sortAttributeAccessPlans(
            $plans === null ? null : static::allNestedPlans($plans));

        foreach ($relatedItems as $relatedItem)
        {
            $nestedText = static::_textFromContent(
                $relatedItem, $itemPlans, $options);

            if ($nestedText) {
                $text[$relatedItem->id] = $nestedText;
            }
        }

        return $text;
    }

    /**
     * Creates a map of text data, where each attribute is mapped to the
     * text data it stores.
     *
     * @param array $text Original text data
     * @param array $options Options to create the map
     * @param array|null $info Information about the text data in the map
     *
     * @return array
     */
    private static function _mapText(
        array $text,
        array $options,
        array &$info = null
    ): array
    {
        $map = [];

        $flatten = $options['flatten'] ?? true;
        $limit = $options['limit'] ?? null;
        $hasLimit = ($limit && $limit !== null);

        $itemOptions = array_merge([], $options);

        $length = 0;
        $reachedLimit = false;

        foreach ($text as $key => $item)
        {
            $itemInfo = [];

            if ($hasLimit) {
                $itemOptions['limit'] = $limit - $length;
            }

            if (is_array($item))
            {
                if ($flatten) {
                    $item = static::_flattenText($item, $itemOptions, $itemInfo);
                } else {
                    $item = static::_mapText($item, $itemOptions, $itemInfo);
                }
            }

            else if (is_string($item)) {
                $item = static::processText($item, $itemOptions, $itemInfo);
            }

            else
            {
                throw new InvalidArgument(
                    "Argument `text` must contain only strings or nested text maps");
            }

            $map[$key] = $item;

            $length += $itemInfo['length'];
            $reachedLimit = $itemInfo['reachedLimit'];

            if ($reachedLimit) break;
        }

        $info = [
            'length' => $length,
            'reachedLimit' => $reachedLimit,
        ];

        return $map;
    }

    /**
     * Flattens given map of text data.
     *
     * @param array $map Map of text data to flatten
     * @param array $options Options on how to flatted the text data
     * @param array|null $info Info about the flattened text data
     *
     * @return string
     */
    private static function _flattenText(
        array $map,
        array $options,
        array &$info = null
    ): string
    {
        $text = '';
        $length = 0;
        $reachedLimit = false;

        $limit = $options['limit'] ?? null;
        $hasLimit = ($limit && $limit !== null);
        $glue = $options['glue'] ?? "\n\n";
        $glueLength = mb_strlen($glue);

        $itemOptions = array_merge([], $options);

        foreach ($map as $item)
        {
            $itemInfo = [];

            if (is_string($item)) {
                $text = static::appendText($text, $item, $options, $itemInfo);
            }

            else if (is_array($item))
            {
                if ($hasLimit) {
                    $itemLimit = $limit - $length;
                    if ($text) $itemLimit -= $glueLength;
                    $itemOptions['limit'] = $itemLimit;
                }

                $itemText = static::_flattenText($item, $itemOptions, $itemInfo);
                $text = $text ? $text.$glue.$itemText : $itemText;
            }

            else
            {
                throw new InvalidArgumentException(
                    "Argument map must contain only strings or nested text maps");
            }
            
            $length += $itemInfo['length'];
            $reachedLimit = $itemInfo['reachedLimit'];

            if ($reachedLimit) break; // we are done!
        }

        if ($hasLimit && !$reachedLimit) {
            $reachedLimit = ($length == $reachedLimit);
        }

        $info = [
            'length' => $length,
            'reachedLimit' => $reachedLimit,
        ];

        return $text;
    }
}