# ArrayPress Expression Parser

A powerful PHP library for evaluating mathematical expressions using BC Math for arbitrary precision arithmetic. This library provides a clean, intuitive interface for parsing and evaluating mathematical expressions, with special integration for WordPress environments.

## Features

- 🧮 **Arbitrary Precision**: Uses BC Math for high-precision calculations
- 🔢 **Multiple Operators**: Supports +, -, *, /, and ^ operators
- 🎯 **Configurable Scale**: Set decimal precision dynamically
- 🛡️ **Input Validation**: Comprehensive expression validation
- 📐 **Shunting Yard Algorithm**: Efficient expression parsing
- 🔒 **Type Safety**: Full type hinting and return type declarations
- ⚡ **Simple Interface**: Easy to understand and implement
- 🔄 **WordPress Integration**: Native WP_Error support when in WordPress environment
- 🚫 **Error Handling**: Flexible error handling for both WordPress and standalone use

## Requirements

- PHP 7.4 or later
- BC Math PHP Extension
- Optional: WordPress (for WP_Error integration)

## Installation

Install via Composer:

```bash
composer require arraypress/expressionparser
```

## Basic Usage

```php
use ArrayPress\Utils\Math\ExpressionParser;

// Initialize parser with 4 decimal places
$parser = new ExpressionParser( 4 );

// WordPress Environment
$result = $parser->evaluate('2 + 3 * (4 - 2)');
if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
} else {
    echo "Result: $result";
}

// Non-WordPress Environment
try {
    $result = $parser->evaluate( '2 + 3 * (4 - 2)' );
    echo "Result: $result";
} catch ( Exception $e ) {
    echo "Error: " . $e->getMessage();
}
```

## Expression Support

### Supported Operators

The library supports the following operators:

```php
// Basic Arithmetic
$result = $parser->evaluate( '10 + 5' );    // Addition
$result = $parser->evaluate( '10 - 5' );    // Subtraction
$result = $parser->evaluate( '10 * 5' );    // Multiplication
$result = $parser->evaluate( '10 / 5' );    // Division

// Power Operation
$result = $parser->evaluate( '2 ^ 3' );     // Exponentiation

// Complex Expressions
$result = $parser->evaluate( '(2 + 3) * 4' );
$result = $parser->evaluate( '2 + 3 * 4' );
$result = $parser->evaluate( '(2 + 3) * (4 - 1)' );
```

## Scale Control

Control the number of decimal places in calculations:

```php
// Set scale during initialization
$parser = new ExpressionParser( 4 );  // 4 decimal places

// Change scale dynamically
$parser->set_scale( 2 );  // Change to 2 decimal places
echo $parser->get_scale();  // Get current scale (returns 2)

// Calculation with new scale
$result = $parser->evaluate( '10 / 3' );  // Returns result with 2 decimal places
```

## Error Handling

### WordPress Environment

```php
$parser = new ExpressionParser();

// Check for errors using WP_Error
$result = $parser->evaluate( '10 / 0' );
if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
    echo $result->get_error_code();
}

// Get the last error
$lastError = $parser->get_last_error();
if ( $lastError instanceof WP_Error ) {
    echo $lastError->get_error_message();
}
```

### Non-WordPress Environment

```php
try {
    $parser = new ExpressionParser();
    $result = $parser->evaluate( '10 / 0' );
} catch ( Exception $e ) {
    echo "Error: " . $e->getMessage();
}
```

## Error Codes

The library uses the following error codes when in a WordPress environment:

- `empty_expression`: Expression string is empty
- `invalid_characters`: Expression contains invalid characters
- `mismatched_parentheses`: Parentheses are not properly matched
- `unknown_operator`: Operator is not supported
- `insufficient_operands`: Not enough operands for operator
- `division_by_zero`: Attempted division by zero
- `invalid_expression`: Expression structure is invalid
- `evaluation_error`: General evaluation error
- `invalid_scale`: Scale value is invalid

## Use Cases

- Mathematical Calculations: Evaluate complex mathematical expressions
- Dynamic Formulas: Process user-defined formulas
- Financial Calculations: High-precision arithmetic operations
- Scientific Computing: Complex mathematical operations
- Formula Validation: Verify mathematical expressions
- Educational Tools: Mathematical expression evaluation
- WordPress Integration: Seamless integration with WordPress applications

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Support

- [Documentation](https://github.com/arraypress/expressionparser)
- [Issue Tracker](https://github.com/arraypress/expressionparser/issues)