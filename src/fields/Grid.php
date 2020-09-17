<?php

/**
 * SupercoolTools plugin for Craft CMS 3.x
 *
 * SupercoolTools
 *
 * @link      http://supercooldesign.co.uk
 * @copyright Copyright (c) 2017 Supercool
 */

namespace supercool\tools\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\helpers\Json;
use supercool\tools\fields\data\GridData;
use yii\db\Schema;

/**
 * Grid Field
 *
 * @author    Supercool
 * @package   SupercoolTools
 * @since     1.0.0
 */
class Grid extends Field implements PreviewableFieldInterface
{

    // Properties
    // =========================================================================

    /**
     * @var int The default value for total columns
     */
    public $totalColumns = 12;

    /**
     * @var int The default value for left pointer
     */
    public $leftDefault = 2;

    /**
     * @var int The default value for right pointer
     */
    public $rightDefault = 10;

    /**
     * @var int The minimum number of columns for the left->right to span
     */
    public $minColumnSpan = 1;

    /**
     * @var int The maximum number of columns for the left->right to span
     */
    public $maxColumnSpan;


    // Static Methods
    // =========================================================================

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        return Craft::t('tools', 'Grid');
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [
            [
                'totalColumns',
                'leftDefault',
                'rightDefault',
                'maxColumnSpan'
            ],
            'number', 'integerOnly' => true
        ];
        $rules[] = [['leftDefault', 'rightDefault'], 'required'];
        $rules[] = [['leftDefault', 'rightDefault'], function ($attribute, $params, $validator) {
            // Ensure defaults are at least min column span width apart
            $minSpan = (int) $this->minColumnSpan;
            $maxSpan = $this->maxColumnSpan ? (int) $this->maxColumnSpan : false;
            $leftDefault = (int) $this->leftDefault;
            $rightDefault = (int) $this->rightDefault;

            if ((int)$this->$attribute > $this->totalColumns) {
                $this->addError($attribute, "Must be lower than the total columns");
            }

            if($leftDefault > $rightDefault)
            {
                $this->addError($attribute,
                    "Left Default cannot be larger than Right Default"
                );
                return;
            }

            if ($leftDefault > ($rightDefault - $minSpan)) {
                $this->addError($attribute,
                    "Difference between Left and Right Defaults must be >= the Minimum Column Span ($minSpan)"
                );
            }

            if($maxSpan) {
                if ( ((int) $this->leftDefault + $maxSpan) < (int) $this->rightDefault ) {
                    $this->addError($attribute,
                        "Difference Between Left and Right Defaults must be <= the Maximum Column Span ($maxSpan)"
                    );
                }
            }
        }];

        return $rules;
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('tools/_components/fields/grid/settings',
            [
                'field' => $this
            ]);
    }


    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Come up with an ID value for 'foo'
        $id = Craft::$app->getView()->formatInputId($this->handle);

        // Figure out what that ID is going to be namespaced into
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);


        return Craft::$app->getView()->renderTemplate('tools/_components/fields/grid/input', array(
            'name' => $this->handle,
            'value' => $value,
            'namespaceId' => $namespacedId
        ));

    }


    /**
     * @inheritDoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {

        if (!$value) {
            $value = new GridData(
                $this->totalColumns,
                $this->leftDefault,
                $this->rightDefault,
                $this->minColumnSpan,
                $this->maxColumnSpan
            );
        }

        if (is_string($value)) {
            $value = json_decode($value);

            // Make sure values from pre 2020 rework are at least 1 column span
            if((int)$value->left >= (int)$value->right) {
                $value->left = $this->leftDefault;
                $value->right = $this->rightDefault;
            }

            $value = new GridData(
                $this->totalColumns,
                $value->left,
                $value->right,
                $this->minColumnSpan,
                $this->maxColumnSpan
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        $value = Json::encode($value);
        return $value;
    }

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [
            [function(Element $element, $params) {
                $handle = $this->handle;
                $newData = $element->$handle;

                if(is_array($newData)) {
                    $newData = (object) $newData;
                }

                $value = new GridData(
                    $this->totalColumns,
                    $newData->left,
                    $newData->right,
                    $this->minColumnSpan,
                    $this->maxColumnSpan
                );

                // Ensure if any errors are present, the field input still receives a grid data model
                $element->$handle = $value;

                $totalColumns = $value->totalColumns;
                $minSpan = $value->minColumnSpan;
                $maxSpan = $value->maxColumnSpan;

                if((!$newData->left && $newData->left !== '0')  || (!$newData->right && $newData->right !== '0')) {
                    $element->addError($handle, "Must provide a left and a right value");
                    return;
                }

                if($value->left > $totalColumns) {
                    $element->addError($handle, "The left value ($value->left) cannot be greater than the total columns available ($totalColumns)");
                    return;
                }

                if($value->right > $totalColumns) {
                    $element->addError($handle, "The right value ($value->right) cannot be greater than the total columns available ($totalColumns)");
                    return;
                }

                if($value->left > $value->right) {
                    $element->addError($handle, "Left value ($value->left) cannot be greater than Right ($value->right)");
                    return;
                }

                if($value->left > ($value->right - $minSpan)) {
                    $element->addError($handle, "Must be at least $minSpan columns wide");
                }

                if($maxSpan) {
                    if(($value->left + $maxSpan) < $value->right) {
                        $element->addError($handle, "Must be less than $maxSpan columns wide");
                    }
                }
            }]
        ];
    }

}
