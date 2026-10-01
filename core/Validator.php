<?php

class Validator
{
    private $errors = [];

    public static function stringValue($value)
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function validateCategory($db, $name, $ignoreId = null)
    {
        $errors = [];
        $name = trim((string) $name);

        if ($name === '') {
            $errors['name'] = 'Category name is required.';
        } elseif (self::length($name) < 2) {
            $errors['name'] = 'Category name must be at least 2 characters.';
        } elseif (self::length($name) > 100) {
            $errors['name'] = 'Category name must not exceed 100 characters.';
        } else {
            $sql = 'SELECT id FROM categories WHERE name = ?';
            $params = [$name];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = (int) $ignoreId;
            }
            if ($db->selectOne($sql, $params)) {
                $errors['name'] = 'A category with this name already exists.';
            }
        }

        return $errors;
    }

    public static function validateProduct($db, $form, $allowInactiveCategory = false)
    {
        $errors = [];
        $name = trim((string) ($form['name'] ?? ''));
        $categoryId = trim((string) ($form['category_id'] ?? ''));
        $price = trim((string) ($form['price'] ?? ''));
        $stock = trim((string) ($form['stock'] ?? ''));
        $description = (string) ($form['description'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Product name is required.';
        } elseif (self::length($name) < 2) {
            $errors['name'] = 'Product name must be at least 2 characters.';
        } elseif (self::length($name) > 200) {
            $errors['name'] = 'Product name must not exceed 200 characters.';
        }

        if ($categoryId === '' || !ctype_digit($categoryId) || (int) $categoryId <= 0) {
            $errors['category_id'] = 'Please select a category.';
        } else {
            $categorySql = 'SELECT id FROM categories WHERE id = ?';
            if (!$allowInactiveCategory) {
                $categorySql .= ' AND status = 1';
            }
            if (!$db->selectOne($categorySql, [(int) $categoryId])) {
                $errors['category_id'] = 'Please select a valid category.';
            }
        }

        if ($price === '') {
            $errors['price'] = 'Product price is required.';
        } elseif (!is_numeric($price)) {
            $errors['price'] = 'Please enter a valid price.';
        } elseif ((float) $price <= 0) {
            $errors['price'] = 'Price must be greater than 0.';
        } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $price) || (float) $price > 99999999.99) {
            $errors['price'] = 'Please enter a valid price with up to 2 decimal places.';
        }

        if ($stock === '') {
            $errors['stock'] = 'Stock quantity is required.';
        } elseif (is_numeric($stock) && (float) $stock < 0) {
            $errors['stock'] = 'Stock quantity cannot be negative.';
        } elseif (!ctype_digit($stock)) {
            $errors['stock'] = is_numeric($stock)
                ? 'Quantity must be a whole number.'
                : 'Please enter a valid quantity.';
        } elseif ((float) $stock > 4294967295) {
            $errors['stock'] = 'Stock quantity is too large.';
        }

        if (strlen($description) > 65535) {
            $errors['description'] = 'Description must fit within the 65,535-byte database limit.';
        }

        return $errors;
    }

    public static function isDuplicateConstraint($error)
    {
        return $error instanceof mysqli_sql_exception && (int) $error->getCode() === 1062;
    }

    public function required($value, $field)
    {
        if (trim((string) $value) === '') {
            $this->errors[$field] = ucfirst($field) . ' is required.';
        }
        return $this;
    }

    public function email($value, $field = 'email')
    {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }
        return $this;
    }

    public function minLength($value, $length, $field)
    {
        if (strlen((string) $value) < $length) {
            $this->errors[$field] = ucfirst($field) . " must be at least {$length} characters.";
        }
        return $this;
    }

    public function matches($value, $other, $field)
    {
        if ($value !== $other) {
            $this->errors[$field] = 'Passwords do not match.';
        }
        return $this;
    }

    public function numeric($value, $field)
    {
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = ucfirst($field) . ' must be a number.';
        }
        return $this;
    }

    public function fails()
    {
        return count($this->errors) > 0;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function first()
    {
        return reset($this->errors) ?: null;
    }

    private static function length($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        $length = preg_match_all('/./us', $value);
        return $length === false ? strlen($value) : $length;
    }
}
