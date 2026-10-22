<?php
declare(strict_types=1);

namespace Src\Validators;

use PDO;

abstract class BaseValidator
{
    protected array $data;
    protected array $errors = [];
    protected PDO $db;

    /**
     * The constructor.
     *
     * @param array $data - POST data
     * @param PDO $db - Database instance
     */
    public function __construct(array $data, PDO $db)
    {
        $this->data = $data;
        $this->db = $db;
    }

    /**
     * Determines if field value is valid by chaining validation methods together
     *
     */
    abstract public function validate(): static;

    /**
     * Returns string containing trimmed value of form field
     *
     * @param string $field - field name
     * @return string - trimmed value
     */
    protected function getValue(string $field): string
    {
        return trim($this->data[$field] ?? '');
    }

    /**
     * Method for fields that are required
     *
     * @param string $field - field name
     * @param string|null $msg - error message
     * @return $this
     */
    public function required(string $field, string $msg = null): static
    {
        if ($this->getValue($field) === '')
        {
            $this->errors[$field] = $msg ?? "Field '$field' is required";
        }
        return $this;
    }

    /**
     * Method for fields requiring a min char count
     *
     * @param string $field - field name
     * @param int $limit - the minimum character limit
     * @param string|null $msg - error message
     * @return $this
     */
    public function min(string $field, int $limit, string $msg = null): static
    {
        if (strlen($this->getValue($field)) < $limit)
        {
            $this->errors[$field] = $msg ?? "Field '$field' must be at least $limit characters";
        }
        return $this;
    }

    /**
     * Method for fields requiring a max char count
     *
     * @param string $field - field name
     * @param int $limit - the max character limit
     * @param string|null $msg - error message
     * @return $this
     */
    public function max(string $field, int $limit, string $msg = null): static
    {
        if (strlen($this->getValue($field)) > $limit)
        {
            $this->errors[$field] = $msg ?? "Field '$field' must be at most $limit characters";
        }
        return $this;
    }

    /**
     * Method for assessing if values in two fields match
     *
     * @param string $field1 - first field name
     * @param string $field2 - second field name
     * @param string|null $msg - error message
     * @return $this
     */
    public function match(string $field1, string $field2, string $msg = null): static
    {
        if ($this->getValue($field1) !== $this->getValue($field2))
        {
            $this->errors[$field1] = $msg ?? "Field '$field1' does not match '$field2'";
        }
        return $this;
    }

    /**
     * Determines if value from field is already present in DB
     *
     * @param string $field - field name
     * @param string $table - DB table name
     * @param string|null $column - DB column name
     * @param $ignoreId - entry ID to be ignore
     * @param string|null $msg - eerror message
     * @return $this
     */
    public function unique(
        string $field,
        string $table,
        string $column = null,
        $ignoreId = null,
        string $msg = null
    ): static
    {
        // if error is already present, return early
        if (isset($this->errors[$field]))
            return $this;

        // if column is not explicitly set, use field name from form
        $column = $column ?? $field;

        // get data from field
        $value = $this->getValue($field);

        // if value is empty, return early
        if ($value == '')
            return $this;

        // whitelist
        $allowedTables =
            [
                'users',
                'clients',
                'client_types',
                'invoices',
                'invoice_items',
                'payments',
                'invoice_payment',
                'expenses',
            ];
        $allowedColumns =
            [
                'id', 'email', 'username'
            ]; //TODO: review if more are needed

        if (!in_array($table, $allowedTables, true) || !in_array($column, $allowedColumns, true))
            throw new \InvalidArgumentException('Invalid table or column name!');

        // prep sql query
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";

        // if ignoreId is set, ignore current list
        if ($ignoreId !== null)
            $sql .= " AND id != :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);

        // if ignoreId is set, bind it
        if ($ignoreId !== null)
            $stmt->bindValue(':id', $ignoreId, PDO::PARAM_INT);

        $stmt->execute();

        if ($stmt->fetchColumn() > 0)
            $this->errors[$field] = $msg ?? "Field '$field' must be unique!";

        return $this;
    }

    /**
     * Determines whether input is valid
     *
     * @return bool - returns bool based on errors array state
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Returns all accumulated errors
     *
     * @return array - error array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}