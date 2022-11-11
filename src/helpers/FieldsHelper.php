<?php
namespace yoannisj\tailor\helpers;

use Craft;
use craft\base\FieldInterface;
use craft\db\mysql\Schema;
use craft\fields\PlainText as PlainTextField;
use craft\fields\BaseRelationField;
use craft\fields\Matrix as MatrixField;

// Computed Text Fields
use besteadfast\preparsefield\fields\PreparseFieldType as PreparseField;
use dodecastudio\autosuggest\fields\AutoSuggestField;
use mmikkel\incognitofield\fields\IncognitoFieldType as IncognitoField;
use codewithkyle\readonly\fields\ReadOnlyField as CodeWithKyleReadOnlyField;
use codemonauts\readonly\fields\ReadonlyField as CodemonautsReadOnlyField;

// Rich-Text Fields
use craft\htmlfield\HtmlField;
use craft\htmlfield\HtmlFieldData;
use craft\redactor\Field as RedactorField;
use craft\ckeditor\Field as CkEditorField;
use spicyweb\tinymce\fields\TinyMCE as TinyMCEField;

use verbb\doxter\fields\Doxter as DoxterField;
use verbb\doxter\fields\data\DoxterData;

// Block-Fields
use benf\neo\Field as NeoField;
use benf\neo\elements\Block as NeoBlock;

use verbb\vizy\fields\VizyField;
use verbb\vizy\models\NodeCollection as VizyNodeCollection;
use verbb\vizy\nodes\VizyBlock;
use verbb\vizy\nodes\Text as VizyText;

use verbb\supertable\fields\SuperTableField;
use verbb\supertable\elements\SuperTableBlockElement as SuperTableBlock;

// SEO-fields
use nystudio107\seomatic\fields\SeoSettings as SeoMaticSettingsField;
use nystudio107\seomatic\models\MetaBundle as SeoMaticMetaBundle;

use ether\seo\fields\SeoField as EtherSeoField;
use ether\seo\models\SeoData as EtherSeoData;

use studioespresso\seofields\fields\SeoField as StudioEspressoSeoField;
use studioespresso\seofields\models\SeoFieldModel as StudioEspressoSeoFieldModel;

use creativeorange\craft\article\Article as ArticleField;
use creativeorange\craft\article\ArticleData as ArticleData;

use yoannisj\tailor\Tailor;

/**
 * Class implementing helper methods to work with Craft content fields
 */
class FieldsHelper
{
    // =Public Methods
    // =========================================================================

   /**
     * Checks whether given field stores text
     *
     * @param FieldInterface $field Field model to inspect
     *
     * @return bool
     */
    public static function isTextField( FieldInterface $field ): bool
    {
        return (static::isPlainTextField($field)
            || static::isComputedTextField($field));
    }

    /**
     * Checks whether given field stores plain text values
     *
     * @param FieldInterface $field Field model to inspect
     *
     * @return bool
     */
    public static function isPlainTextField( FieldInterface $field ): bool
    {
        return ($field instanceof PlainTextField
            || $field instanceof AutoSuggestField
            || $field instanceof IncognitoField
            || $field instanceof CodeWithKyleReadOnlyField
            || $field instanceof CodemonautsReadOnlyField);
    }

    /**
     * Checks whetehr given field stores computed/pre-defined text values
     * 
     * @param FieldInterface $field Field model to inspect
     * @return bool
     */
    public static function isComputedTextField( FieldInterface $field ): bool
    {
        if ($field instanceof PreparseField) {
            return static::isPreparseTextField($field);
        }

        return ($field instanceof IncognitoField
            || $field instanceof CodeWithKyleReadOnlyField
            || $field instanceof CodemonautsReadOnlyField);
    }

    /**
     * Checks whether given Preparse field stores text (instead of data).
     *
     * @param PreparseField $field 
     *
     * @return bool
     */
    public static function isPreparseTextField( PreparseField $field ): bool
    {
        $textPreparseFields = Tailor::$plugin->getSettings()->textPreparseFields;

        if ($textPreparseFields && in_array($field->handle, $textPreparseFields)) {
            return true;
        }

        return ($field->columnType == Schema::TYPE_TEXT
            && $field->allowSelect == true);
    }

    /**
     * Checks whether given field stores multi-line text.
     *
     * @param FieldInterface $field 
     *
     * @return bool
     */
    public static function isMultiLineTextField( FieldInterface $field ): bool
    {
        if ($field instanceof PlainTextField && $field->multiline == true) {
            return true;
        }
        
        return ($field instanceof PreparseField
            && static::isPreparseTextField($field)
            && $field->textareaRows > 1);
    }

    /**
     * Checks whether given field stores rich-text values (i.e. formatted text)
     *
     * @param FieldInterface $field Field model to inspect
     *
     * @return bool
     */
    public static function isRichTextField( FieldInterface $field ): bool
    {
        return ($field instanceof RedactorField
            || $field instanceof CkEditorField
            || $field instanceof TinyMCEField
            || $field instanceof DoxterField
            || $field instanceof VizyField
            || $field instanceof ArticleField);
    }

    /**
     * Checks whetehr given field stores SEO data
     * 
     * @param FieldInterface $field Field model to inspect
     * @return bool
     */
    public static function isSeoField( FieldInterface $field ): bool
    {
        return ($field instanceof SeoMaticSettingsField
            || $field instanceof EtherSeoField
            || $field instanceof StudioEspressoSeoField);
    }

    /**
     * Checks whether given field model stores block elements
     *
     * @param FieldInterface $field Field model to inspect
     *
     * @return bool
     */
    public static function isBlocksField( FieldInterface $field ): bool
    {
        return ($field instanceof MatrixField
            || $field instanceof NeoField
            || $field instanceof SuperTableField);
    }

    /**
     * Checks whether given field model stores content layouts
     *
     * @param FieldInterface $field Field model to inspect
     *
     * @return bool
     */
    public static function isLayoutField( FieldInterface $field ): bool
    {
        return (static::isBlocksField($field)
            || $field instanceof VizyField
            || $field instanceof ArticleField);
    }
    
    /**
     * Checks whether given field model stores element relations
     *
     * @param FieldInterface $field Field model to inspect
     *
     * @return bool
     */
    public static function isRelationsField( FieldInterface $field ): bool
    {
        return ($field instanceof BaseRelationField);
    }
}