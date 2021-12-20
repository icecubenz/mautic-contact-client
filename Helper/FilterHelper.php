<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactClientBundle\Helper;

use Exception;

/**
 * Class FilterHelper.
 */
class FilterHelper
{
    /** @var int */
    protected $max_string_length = 32;

    /** @var array */
    protected $errors = [];

    /** @var array */
    protected $operators = [
        'equal'            => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'not_equal'        => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'in'               => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'not_in'           => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'less'             => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'less_or_equal'    => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'greater'          => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'greater_or_equal' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'between'          => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'begins_with'      => ['accept_values' => true, 'apply_to' => ['string']],
        'not_begins_with'  => ['accept_values' => true, 'apply_to' => ['string']],
        'contains'         => ['accept_values' => true, 'apply_to' => ['string']],
        'not_contains'     => ['accept_values' => true, 'apply_to' => ['string']],
        'ends_with'        => ['accept_values' => true, 'apply_to' => ['string']],
        'not_ends_with'    => ['accept_values' => true, 'apply_to' => ['string']],
        'is_empty'         => ['accept_values' => false, 'apply_to' => ['string']],
        'is_not_empty'     => ['accept_values' => false, 'apply_to' => ['string']],
        'is_null'          => ['accept_values' => false, 'apply_to' => ['string', 'number', 'datetime']],
        'is_not_null'      => ['accept_values' => false, 'apply_to' => ['string', 'number', 'datetime']],
        'regex'            => ['accept_values' => true, 'apply_to' => ['string']],
        'not_regex'        => ['accept_values' => true, 'apply_to' => ['string']],
    ];

    /**
     * @param bool $clean
     *
     * @return array
     */
    public function getErrors($clean = true)
    {
        return $this->errors;
    }

    /**
     * Use a jQuery Query Builder JSON to evaluate the context.
     *
     * @param string      $json
     * @param array       $context       An array of data to be evaluated
     * @param bool|string $noRulesResult the result should the rules be missing/invalid,
     *                                   leave as true to succeed by default
     *
     * @return bool return true if the context passes the filters of $json
     *
     * @throws Exception
     */
    public function filter($json, array $context = [], $noRulesResult = true)
    {
        // Pre-validate the string.
        if (!strlen($json)) {
            if (!$noRulesResult) {
                $this->setError('No rules to evaluate.');
            }

            return $noRulesResult;
        }

        $query = $this->decodeJSON($json);

        // Post validate the json.
        if (!isset($query->rules) || !is_array($query->rules) || count($query->rules) < 1) {
            if (!$noRulesResult) {
                $this->setError('No rules to evaluate.');
            }

            return $noRulesResult;
        }

        return $this->loopThroughRules($query->rules, $context, isset($query->condition) ? $query->condition : 'AND');
    }

    private function setError($string)
    {
        $this->errors[] = $string;
    }

    /**
     * Decode the given JSON.
     *
     * @param $json
     *
     * @return mixed
     *
     * @throws Exception
     */
    private function decodeJSON($json)
    {
        $query = json_decode($json);
        if (json_last_error()) {
            throw new Exception('JSON parsing threw an error: '.json_last_error_msg());
        }
        if (!is_object($query)) {
            throw new Exception('The query is not valid JSON');
        }

        return $query;
    }

    /**
     * Called by parse, loops through all the rules to find out if nested or not.
     *
     * @param array  $rules
     * @param array  $context
     * @param string $condition
     *
     * @return bool
     *
     * @throws Exception
     * @throws Exception
     */
    protected function loopThroughRules(array $rules, array $context = [], $condition = 'AND')
    {
        $result    = true;
        $condition = strtolower($condition);
        $this->validateCondition($condition);
        if (count($rules) == 0) {
            return $result;
        }
        foreach ($rules as $rule) {
            if ($this->isNested($rule)) {
                $result = $this->loopThroughRules($rule->rules, $context, $rule->condition);
            } else {
                $result = $this->evaluate($rule, $context);
            }
            // Conditions upon which we can stop evaluation.
            if ('and' == $condition && false === $result) {
                break;
            } else {
                if ('or' == $condition && true === $result) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Make sure that a condition is either 'or' or 'and'.
     *
     * @param $condition
     *
     * @return string
     *
     * @throws Exception
     */
    protected function validateCondition($condition)
    {
        $condition = trim(strtolower($condition));

        if ('and' !== $condition && 'or' !== $condition) {
            throw new Exception("Condition can only be one of: 'and', 'or'.");
        }

        return $condition;
    }

    /**
     * Evaluate: The money maker!
     *
     * @param       $rule
     * @param array $context
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function evaluate($rule, array $context = [])
    {
        try {
            $ruleValue = $this->getValueFromRule($rule);
        } catch (Exception $e) {
            $this->setError('Error attempting to get a value from a rule: '.$this->minify($e->getMessage()));

            return false;
        }

        try {
            $contextValue = $this->getValueFromContext($rule, $context);
        } catch (Exception $e) {
            $this->setError('Error attempting to get a value from context: '.$this->minify($e->getMessage()));

            return false;
        }

        $result = $this->evaluateRuleAgainstContext($rule, $contextValue, $ruleValue);
        if (!$result) {
            $this->setError(
                $rule->field.' must be '.rtrim(str_replace('_', ' ', $rule->operator), 's').' '.
                $this->minify($ruleValue).
                ' value was '.$this->minify($contextValue).'.'
            );
        }

        return $result;
    }

    /**
     * Ensure that the value is correct for the rule, try and set it if it's not.
     *
     * @param $rule
     *
     * @return mixed|string|null
     *
     * @throws Exception
     */
    protected function getValueFromRule($rule)
    {
        $value = $this->getRuleValue($rule);

        if (
            isset($rule->operator)
            && isset($this->operators[$rule->operator])
            && isset($this->operators[$rule->operator]['accept_values'])
            && false === $this->operators[$rule->operator]['accept_values']
        ) {
            return $this->operatorValueWhenNotAcceptingOne($rule);
        }

        if (
            !isset($rule->operator)
            || !isset($rule->field)
        ) {
            return null;
        }

        return $this->getCorrectValue($rule->operator, $rule, $value);
    }

    /**
     * get a value for a given rule.
     * throws an exception if the rule is not correct.
     *
     * @param $rule
     *
     * @return mixed
     *
     * @throws Exception
     */
    private function getRuleValue($rule)
    {
        if (!$this->checkRuleCorrect($rule)) {
            $this->setError('Rules are missconfigured.');
        }

        return $rule->value;
    }

    /**
     * Check if a given rule is correct.
     * Just before making a query for a rule, we want to make sure that the field, operator and value are set.
     *
     * @param $rule
     *
     * @return bool true if values are correct
     */
    protected function checkRuleCorrect($rule)
    {
        if (!isset($rule->value)) {
            // We will assume a missing value for the rule is to be interpreted as empty.
            $rule->value = null;
        }
        if (!isset($rule->id, $rule->field, $rule->type, $rule->input, $rule->operator)) {
            return false;
        }
        if (!isset($this->operators[$rule->operator])) {
            return false;
        }

        return true;
    }

    /**
     * Give back the correct value when we don't accept one.
     *
     * @param $rule
     *
     * @return string|null
     */
    protected function operatorValueWhenNotAcceptingOne($rule)
    {
        if ('is_empty' == $rule->operator || 'is_not_empty' == $rule->operator) {
            return '';
        }

        return null;
    }

    /**
     * Ensure that the value for a field is correct.
     *
     * @param string $operator
     * @param        $rule
     * @param        $value
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function getCorrectValue($operator, $rule, $value)
    {
        $requireArray = $this->operatorRequiresArray($operator);

        return $this->enforceArrayOrString($requireArray, $value, $rule->field);
    }

    /**
     * Determine if an operator (LIKE/IN) requires an array.
     *
     * @param $operator
     *
     * @return bool
     */
    protected function operatorRequiresArray($operator)
    {
        return in_array($operator, ['in', 'not_in', 'between']);
    }

    /**
     * Enforce whether the value for a given field is the correct type.
     *
     * @param bool   $requireArray value must be an array
     * @param mixed  $value        the value we are checking against
     * @param string $field        the field that we are enforcing
     *
     * @return mixed value after enforcement
     *
     * @throws Exception if value is not a correct type
     */
    protected function enforceArrayOrString($requireArray, $value, $field)
    {
        $this->checkFieldIsAnArray($requireArray, $value, $field);

        if (!$requireArray && is_array($value)) {
            return $this->convertArrayToFlatValue($field, $value);
        }

        return $value;
    }

    /**
     * Ensure that a given field is an array if required.
     *
     * @param bool   $requireArray
     * @param        $value
     * @param string $field
     *
     * @throws Exception
     *
     * @see enforceArrayOrString
     */
    protected function checkFieldIsAnArray($requireArray, &$value, $field)
    {
        if ($requireArray && is_string($value)) {
            $value = (array) str_getcsv($value);
        }
        if ($requireArray && !is_array($value)) {
            throw new Exception("Field ($field) should be an array, but it isn't.");
        }
    }

    /**
     * Convert an array with just one item to a string.
     * In some instances, and array may be given when we want a string.
     *
     * @param string $field
     * @param        $value
     *
     * @return mixed
     *
     * @throws Exception
     *
     * @see enforceArrayOrString
     */
    protected function convertArrayToFlatValue($field, $value)
    {
        if (1 !== count($value)) {
            throw new Exception("Field ($field) should not be an array, but it is.");
        }

        return $value[0];
    }

    /**
     * @param $string
     *
     * @return string
     */
    private function minify($string)
    {
        if (is_array($string)) {
            $string = implode(',', $string);
        }
        $start = microtime(true);
        // Remove HTML since this will be shown in the UI at some point.
        $string = strip_tags($string);
        // Remove extra/duplicate whitespace.
        $string = preg_replace('/\s+/', ' ', $string);
        // Trim the ends.
        $string = trim($string);
        // Cut length to avoid spamming logs.
        if (strlen($string) > $this->max_string_length) {
            $string = substr($string, 0, $this->max_string_length).'…';
        } elseif (empty($string)) {
            $string = 'empty';
        }

        return $string;
    }

    /**
     * Take a (potentially nested) field name and get the literal value from the contextual array.
     *
     * @param $rule
     * @param $context
     *
     * @return bool
     */
    protected function getValueFromContext($rule, $context)
    {
        if (!isset($rule->field)) {
            return null;
        }

        // Fields are only nested one level deep and flattened thereafter.
        $parts = explode('.', $rule->field);
        $key   = array_shift($parts);
        if (isset($context[$key]) && count($parts)) {
            $context = $context[$key];
            $key     = implode('.', $parts);
        } else {
            $key = $rule->field;
        }

        return isset($context[$key]) ? $context[$key] : false;
    }

    /**
     * Convert an incomming rule from jQuery QueryBuilder to the Doctrine Querybuilder.
     *
     * (This used to be part of evaluate, where the name made sense, but I pulled it
     * out to reduce some duplicated code inside JoinSupportingQueryBuilder)
     *
     * @param $rule
     * @param $contextValue
     * @param $ruleValue
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function evaluateRuleAgainstContext($rule, $contextValue, $ruleValue)
    {
        $operator = strtolower($rule->operator);
        switch ($operator) {
            case 'in':
                return in_array($contextValue, $ruleValue);
                break;
            case 'not_in':
                return !in_array($contextValue, $ruleValue);
                break;
            case 'between':
                $min = min($ruleValue);
                $max = max($ruleValue);

                return $contextValue == $min || $contextValue == $max || ($contextValue > $min && $contextValue < $max);
                break;
            case 'is_null':
                return null === $contextValue;
                break;
            case 'is_not_null':
                return null !== $contextValue;
                break;
            case 'equal':
                return $contextValue == $ruleValue;
                break;
            case 'not_equal':
                return $contextValue !== $ruleValue;
                break;
            case 'less':
                return $contextValue < $ruleValue;
                break;
            case 'less_or_equal':
                return $contextValue <= $ruleValue;
                break;
            case 'greater':
                return $contextValue > $ruleValue;
                break;
            case 'greater_or_equal':
                return $contextValue >= $ruleValue;
                break;
            case 'begins_with':
                return 0 === strpos($contextValue, $ruleValue);
                break;
            case 'not_begins_with':
                return 0 !== strpos($contextValue, $ruleValue);
                break;
            case 'contains':
                return strpos($contextValue, $ruleValue) > -1;
                break;
            case 'not_contains':
                return false === strpos($contextValue, $ruleValue);
                break;
            case 'ends_with':
                return substr($contextValue, -strlen($ruleValue)) === $ruleValue;
                break;
            case 'not_ends_with':
                return substr($contextValue, -strlen($ruleValue)) !== $ruleValue;
                break;
            case 'is_empty':
                return empty(trim($contextValue));
                break;
            case 'is_not_empty':
                return !empty(trim($contextValue));
                break;
            case 'regex':
                if (
                    '/' !== substr($ruleValue, 0, 1)
                    && '/' !== substr($ruleValue, -1, 1)
                ) {
                    $ruleValue = '/'.$ruleValue.'/';
                }
                try {
                    return 1 === preg_match($ruleValue, $contextValue);
                } catch (Exception $e) {
                    $this->setError('Invalid regex pattern.');

                    return false;
                }
                break;
            case 'not_regex':
                if (
                    '/' !== substr($ruleValue, 0, 1)
                    && '/' !== substr($ruleValue, -1, 1)
                ) {
                    $ruleValue = '/'.$ruleValue.'/';
                }
                try {
                    return 0 === preg_match($ruleValue, $contextValue);
                } catch (Exception $e) {
                    $this->setError('Invalid regex pattern.');

                    return false;
                }
                break;
        }
    }

    /**
     * Determine if we have nested rules to evaluate.
     *
     * @param  $rule
     *
     * @return bool
     */
    protected function isNested($rule)
    {
        if (isset($rule->rules) && is_array($rule->rules)) {
            return true;
        }

        return false;
    }

    /*
     * Kept for posterity. A mechanism to expound the dot-notated fields to a nested array recursively.
     * Not currently used because our contextual arrays are flattened externally after the first level.
     *
     * @param $context
     */
    private function expoundContext(&$context)
    {
        foreach ($context as $key => &$value) {
            if (is_array($value) || is_object($value)) {
                $this->expoundContext($value);
            }
            $t    = &$context;
            $keys = explode('.', $key);
            if (count($keys) > 1) {
                foreach ($keys as $subKey) {
                    if (!isset($t[$subKey])) {
                        $t[$subKey] = [];
                    }
                    $t = &$t[$subKey];
                }
                $t = $value;
                unset($context[$key]);
            }
        }
    }
}
