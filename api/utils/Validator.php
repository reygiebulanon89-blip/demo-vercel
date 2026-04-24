<?php

class Validator {
    private static $errors = [];

    public static function validate($data, $rules) {
        self::$errors = [];
        
        foreach ($rules as $field => $field_rules) {
            $value = $data[$field] ?? null;
            $rules_array = is_string($field_rules) ? explode('|', $field_rules) : $field_rules;
            
            foreach ($rules_array as $rule) {
                self::applyRule($field, $value, $rule);
            }
        }
        
        return empty(self::$errors);
    }

    private static function applyRule($field, $value, $rule) {
        $rule_parts = explode(':', $rule);
        $rule_name = trim($rule_parts[0]);
        $rule_param = $rule_parts[1] ?? null;

        switch ($rule_name) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    self::$errors[$field][] = "$field is required";
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    self::$errors[$field][] = "$field must be a valid email";
                }
                break;

            case 'min':
                $min = (int)$rule_param;
                if ($value && strlen($value) < $min) {
                    self::$errors[$field][] = "$field must be at least $min characters";
                }
                break;

            case 'max':
                $max = (int)$rule_param;
                if ($value && strlen($value) > $max) {
                    self::$errors[$field][] = "$field must not exceed $max characters";
                }
                break;

            case 'numeric':
                if ($value && !is_numeric($value)) {
                    self::$errors[$field][] = "$field must be numeric";
                }
                break;

            case 'in':
                $allowed = explode(',', $rule_param);
                $allowed = array_map('trim', $allowed);
                if ($value && !in_array($value, $allowed)) {
                    self::$errors[$field][] = "$field must be one of: " . implode(', ', $allowed);
                }
                break;

            case 'unique':
                // This would require database connection, handled separately
                break;

            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    self::$errors[$field][] = "$field must be a valid URL";
                }
                break;

            case 'string':
                if ($value && !is_string($value)) {
                    self::$errors[$field][] = "$field must be a string";
                }
                break;

            case 'array':
                if ($value && !is_array($value)) {
                    self::$errors[$field][] = "$field must be an array";
                }
                break;
        }
    }

    public static function getErrors() {
        return self::$errors;
    }

    public static function getFirstError() {
        if (empty(self::$errors)) {
            return null;
        }
        
        $first_field = array_key_first(self::$errors);
        return self::$errors[$first_field][0] ?? null;
    }

    public static function hasErrors() {
        return !empty(self::$errors);
    }
}
