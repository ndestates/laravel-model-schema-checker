# Model-Schema Consistency Check Script

This script inspects all Eloquent models in the `app/Models` directory and compares their `$fillable` attributes with the columns in the corresponding database tables.

It is designed to help developers identify inconsistencies between models and the database schema, which can lead to mass-assignment vulnerabilities or unexpected errors.

## Key Features

- **Model Discovery**: Automatically scans the `app/Models` directory to find all model classes.
- **Schema Inspection**: Retrieves the column listing for each model's database table.
- **Discrepancy Reporting**: Identifies and logs the following issues:
  - Fields listed in a model's `$fillable` array that are missing from the database table.
  - Database table columns that are not included in the model's `$fillable` array.
- **Logging**: Outputs all findings to a timestamped log file (e.g., `YYYY-MM-DD-HHMMSS-check.log`) in the project root.

## How to Run

1. Ensure the script is located in the root of your Laravel project.
2. Execute it from the command line.

    **Using DDEV (if applicable):**
    ```bash
    ddev exec php check.php
    ```

    **Using standard PHP CLI:**
    ```bash
    php check.php
    ```

## Available Commands

```bash
php check.php                           # Compare models with database
php check.php --fix                     # Fix model fillable properties automatically
php check.php --dry-run                 # Show what would be changed without applying
php check.php --generate-migrations     # Generate Laravel migrations
php check.php --check-filament          # Check Filament relationship integrity
php check.php --check-all               # Run all available checks (model comparison, Filament relationships)
./run-checker.sh --backup --fix         # Auto-detect environment and fix models
```

## Configuration

You can customize the script's behavior by modifying these variables at the top of `check.php`:

- `$modelsDir`: The path to your models directory (defaults to `app/Models`).
- `$excludedFields`: An array of common fields to ignore during the comparison (e.g., `id`, `created_at`).
- `$databaseConnection`: The name of the database connection to use.

## Output

The script will create a log file in the project root, such as `2025-07-09-123456-check.log`. This file will contain a detailed report of the comparison for each model, highlighting any fields that are mismatched between the model and the database.

## Security Considerations

- **Mass-Assignment Vulnerabilities**: The script identifies discrepancies in the `$fillable` array, which is critical for preventing mass-assignment attacks. If fields in `$fillable` do not match the database schema, it could allow unintended data manipulation.
- **Mitigation Strategies**:
  - Always review and update the `$fillable` array to include only trusted fields.
  - Use Laravel's `guarded` property as an alternative or in conjunction with `$fillable` for added protection.
  - Regularly run this script during development and before deployments to maintain schema consistency.
  - Implement additional validation using form requests or model events to sanitize input.

## TODO: Enhance check.php for Database Schema Checks

1. **Detect and Log Missing Tables or Fields:**  
   In `check.php`, add code to query the database schema and check for missing tables or fields. If issues are found, log SQL commands to a file (e.g., `error.log`) as executable code blocks. This allows users to safely apply fixes one by one.

2. **Security Considerations:**  
   - Use prepared statements for all database queries to prevent SQL injection.  
   - Validate all user inputs and database connections.  
   - Ensure logs are written to a secure, non-public file to avoid exposing sensitive SQL commands.

Example Code Snippet for check.php (to be added in the appropriate function):  
```php
<?php
// Assuming you have a database connection established, e.g., using PDO
try {
    $pdo = new PDO('mysql:host=localhost;dbname=yourdb', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // Enable exceptions for error handling

    // Check if a table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'your_table_name'");
    if ($stmt->rowCount() == 0) {
        // Log the SQL command to create the table
        $sqlCommand = "CREATE TABLE your_table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            field1 VARCHAR(255) NOT NULL
        );";  // Customize based on schema needs
        
        // Write to log file as a code block
        $logMessage = "```\n$sqlCommand\n```";  // Format as Markdown code block for easy copying
        file_put_contents('error.log', $logMessage . PHP_EOL, FILE_APPEND);
        echo "Table 'your_table_name' is missing. SQL command logged to error.log for execution.\n";
    }

    // Similarly, check for missing fields and log ALTER TABLE commands
    // Example for adding a field:
    $checkField = $pdo->query("SHOW COLUMNS FROM your_table_name LIKE 'missing_field'");
    if ($checkField->rowCount() == 0) {
        $alterCommand = "ALTER TABLE your_table_name ADD missing_field VARCHAR(255) NOT NULL;";
        $logMessage = "```\n$alterCommand\n```";
        file_put_contents('error.log', $logMessage . PHP_EOL, FILE_APPEND);
        echo "Field 'missing_field' is missing. SQL command logged to error.log for execution.\n";
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());  // Log errors securely
    echo "An error occurred. Check logs for details.\n";
}
?>