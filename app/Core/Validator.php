<?php
declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }
    
    public function validate(): bool
    {
        foreach ($this->rules as $field => $rules) {
            $fieldRules = is_string($rules) ? explode('|', $rules) : $rules;
            $this->validateField($field, $fieldRules);
        }
        
        return empty($this->errors);
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    private function validateField(string $field, array $rules): void
    {
        $value = $this->data[$field] ?? null;
        
        foreach ($rules as $rule) {
            if (str_contains($rule, ':')) {
                [$ruleName, $parameter] = explode(':', $rule, 2);
            } else {
                $ruleName = $rule;
                $parameter = null;
            }
            
            $this->applyRule($field, $value, $ruleName, $parameter);
        }
    }
    
    private function applyRule(string $field, mixed $value, string $rule, ?string $parameter): void
    {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "Field {$field} is required");
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "Field {$field} must be a valid email");
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < (int)$parameter) {
                    $this->addError($field, "Field {$field} must be at least {$parameter} characters");
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > (int)$parameter) {
                    $this->addError($field, "Field {$field} must not exceed {$parameter} characters");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "Field {$field} must be numeric");
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "Field {$field} must be an integer");
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "Field {$field} must be a valid URL");
                }
                break;
                
            case 'in':
                $allowedValues = explode(',', $parameter);
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    $this->addError($field, "Field {$field} must be one of: " . implode(', ', $allowedValues));
                }
                break;
                
            case 'regex':
                if (!empty($value) && !preg_match($parameter, $value)) {
                    $this->addError($field, "Field {$field} format is invalid");
                }
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (($this->data[$confirmField] ?? null) !== $value) {
                    $this->addError($field, "Field {$field} confirmation does not match");
                }
                break;
                
            case 'unique':
                // This would require database access, implement based on your needs
                break;
        }
    }
    
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }
}
