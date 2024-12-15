<?php
/**
 * Expression Handler Class
 *
 * Handles mathematical expression evaluation using the Shunting Yard algorithm
 * and Reverse Polish Notation (RPN). Supports arbitrary precision arithmetic
 * using PHP's BC Math functions. Includes WordPress integration for error
 * handling when in a WordPress environment.
 *
 * Example usage:
 * ```php
 * $parser = new ExpressionParser(4); // 4 decimal places
 *
 * // WordPress Environment
 * $result = $parser->evaluate('2 + 3 * (4 - 2)');
 * if (is_wp_error($result)) {
 *     echo $result->get_error_message();
 * }
 *
 * // Non-WordPress Environment
 * try {
 *     $result = $parser->evaluate('2 + 3 * (4 - 2)');
 * } catch (Exception $e) {
 *     echo $e->getMessage();
 * }
 * ```
 *
 * @package     ArrayPress/Utils
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\Utils\Math;

use Exception;
use InvalidArgumentException;

class ExpressionParser {

	/**
	 * Defines supported operators with their properties
	 *
	 * Each operator has:
	 * - precedence: Operator precedence (higher number = higher precedence)
	 * - associativity: 'L' for left-associative, 'R' for right-associative
	 * - function: BC Math function to use for the operation
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private const OPERATORS = [
		'+' => [
			'precedence'    => 1,
			'associativity' => 'L',
			'function'      => 'bcadd'
		],
		'-' => [
			'precedence'    => 1,
			'associativity' => 'L',
			'function'      => 'bcsub'
		],
		'*' => [
			'precedence'    => 2,
			'associativity' => 'L',
			'function'      => 'bcmul'
		],
		'/' => [
			'precedence'    => 2,
			'associativity' => 'L',
			'function'      => 'bcdiv'
		],
		'^' => [
			'precedence'    => 3,
			'associativity' => 'R',
			'function'      => 'bcpow'
		]
	];

	/**
	 * Number of decimal places for calculations
	 *
	 * @var int
	 */
	private int $scale = 4;

	/**
	 * Store the last error
	 *
	 * @var \WP_Error|Exception|null
	 */
	private $last_error = null;

	/**
	 * Constructor
	 *
	 * @param int $scale Number of decimal places for calculations (default: 4)
	 */
	public function __construct( int $scale = 4 ) {
		$this->scale = $scale;
	}

	/**
	 * Set the number of decimal places for calculations
	 *
	 * @param int $scale Number of decimal places (must be >= 0)
	 *
	 * @return bool|\WP_Error Returns true on success, WP_Error on failure in WordPress
	 * @throws InvalidArgumentException In non-WordPress environment if invalid
	 * @throws Exception
	 */
	public function set_scale( int $scale ) {
		if ( $scale < 0 ) {
			return $this->handle_error(
				"Scale must be a non-negative integer",
				'invalid_scale'
			);
		}

		$this->scale = $scale;

		return true;
	}

	/**
	 * Get the current scale (number of decimal places)
	 *
	 * @return int Current scale
	 */
	public function get_scale(): int {
		return $this->scale;
	}

	/**
	 * Get the last error if any
	 *
	 * @return \WP_Error|Exception|null
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Check if we're in a WordPress environment
	 *
	 * @return bool
	 */
	private function is_wordpress(): bool {
		return function_exists( 'wp_error' ) && class_exists( 'WP_Error' );
	}

	/**
	 * Handle errors based on environment
	 *
	 * @param string $message Error message
	 * @param string $code    Error code
	 *
	 * @return \WP_Error|void Returns WP_Error in WordPress environment
	 * @throws Exception In non-WordPress environment
	 */
	private function handle_error( string $message, string $code = 'expression_error' ) {
		$this->last_error = $this->is_wordpress()
			? new \WP_Error( $code, $message )
			: new Exception( $message );

		if ( ! $this->is_wordpress() ) {
			throw $this->last_error;
		}

		return $this->last_error;
	}

	/**
	 * Evaluates a mathematical expression
	 *
	 * Converts infix notation to postfix (RPN) and evaluates the result.
	 *
	 * @param string $expression Mathematical expression to evaluate
	 *
	 * @return string|int|\WP_Error Evaluated result or WP_Error in WordPress environment
	 * @throws Exception In non-WordPress environment if expression is invalid
	 */
	public function evaluate( string $expression ) {
		try {
			// Validate the expression
			$validation = $this->validate( $expression );
			if ( $validation !== true ) {
				return $validation;
			}

			// Convert to postfix and evaluate
			$output_queue = $this->infix_to_postfix( $expression );

			return $this->evaluate_rpn( $output_queue );
		} catch ( Exception $e ) {
			return $this->handle_error( $e->getMessage(), 'evaluation_error' );
		}
	}

	/**
	 * Validates the expression for basic syntax errors
	 *
	 * @param string $expression Expression to validate
	 *
	 * @return bool|\WP_Error Returns true if valid, WP_Error in WordPress if invalid
	 * @throws Exception In non-WordPress environment if invalid
	 */
	private function validate( string $expression ) {
		// Remove whitespace for validation
		$expression = trim( $expression );

		if ( empty( $expression ) ) {
			return $this->handle_error( "Expression cannot be empty.", 'empty_expression' );
		}

		// Check for valid characters
		$valid_chars = array_merge(
			array_keys( self::OPERATORS ),
			[ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '(', ')', ' ' ]
		);
		$invalid     = str_replace( $valid_chars, '', $expression );

		if ( ! empty( $invalid ) ) {
			return $this->handle_error(
				"Invalid characters in expression: " . $invalid,
				'invalid_characters'
			);
		}

		return $this->validate_parentheses( $expression );
	}

	/**
	 * Validates parentheses matching in the expression
	 *
	 * @param string $expression Expression to validate
	 *
	 * @return bool|\WP_Error Returns true if valid, WP_Error in WordPress if invalid
	 * @throws Exception In non-WordPress environment if invalid
	 */
	private function validate_parentheses( string $expression ) {
		$count = 0;
		for ( $i = 0; $i < strlen( $expression ); $i ++ ) {
			if ( $expression[ $i ] === '(' ) {
				$count ++;
			} elseif ( $expression[ $i ] === ')' ) {
				$count --;
			}
			if ( $count < 0 ) {
				return $this->handle_error(
					"Mismatched parentheses: unexpected ')'",
					'mismatched_parentheses'
				);
			}
		}
		if ( $count > 0 ) {
			return $this->handle_error(
				"Mismatched parentheses: missing ')'",
				'mismatched_parentheses'
			);
		}

		return true;
	}

	/**
	 * Converts infix notation to postfix (RPN) using Shunting Yard algorithm
	 *
	 * @param string $expression Expression in infix notation
	 *
	 * @return array Expression tokens in postfix notation
	 * @throws Exception If expression contains syntax errors
	 */
	private function infix_to_postfix( string $expression ): array {
		$output_queue   = [];
		$operator_stack = [];

		// Remove spaces and tokenize
		$expression = str_replace( ' ', '', $expression );
		$tokens     = $this->tokenize_expression( $expression );

		foreach ( $tokens as $token ) {
			if ( is_numeric( $token ) ) {
				$output_queue[] = $token;
			} elseif ( isset( self::OPERATORS[ $token ] ) ) {
				$this->process_operator( $token, $operator_stack, $output_queue );
			} elseif ( $token === '(' ) {
				$operator_stack[] = $token;
			} elseif ( $token === ')' ) {
				$this->process_right_parenthesis( $operator_stack, $output_queue );
			}
		}

		// Empty remaining operators to output queue
		while ( ! empty( $operator_stack ) ) {
			$operator = array_pop( $operator_stack );
			if ( $operator === '(' || $operator === ')' ) {
				throw new Exception( "Mismatched parentheses" );
			}
			$output_queue[] = $operator;
		}

		return $output_queue;
	}

	/**
	 * Tokenizes the expression into individual elements
	 *
	 * @param string $expression Expression to tokenize
	 *
	 * @return array Array of tokens
	 */
	private function tokenize_expression( string $expression ): array {
		return preg_split(
			'/(?<=[\d)])(?=[^0-9.])|(?<=[^0-9.])(?=[\d(])/',
			$expression,
			- 1,
			PREG_SPLIT_NO_EMPTY
		);
	}

	/**
	 * Processes an operator according to the Shunting Yard algorithm
	 *
	 * @param string $operator Current operator
	 * @param array  $stack    Operator stack
	 * @param array  $output   Output queue
	 */
	private function process_operator( string $operator, array &$stack, array &$output ): void {
		while ( ! empty( $stack ) && end( $stack ) !== '(' && isset( self::OPERATORS[ end( $stack ) ] ) ) {
			$top_operator = end( $stack );
			if ( $this->should_pop_operator( $operator, $top_operator ) ) {
				$output[] = array_pop( $stack );
				continue;
			}
			break;
		}
		$stack[] = $operator;
	}

	/**
	 * Determines if the top operator should be popped based on precedence and associativity
	 *
	 * @param string $currentOp Current operator
	 * @param string $topOp     Top operator on stack
	 *
	 * @return bool True if top operator should be popped
	 */
	private function should_pop_operator( string $currentOp, string $topOp ): bool {
		$current_op_info = self::OPERATORS[ $currentOp ];
		$top_op_info     = self::OPERATORS[ $topOp ];

		return ( $current_op_info['associativity'] === 'L' &&
		         $current_op_info['precedence'] <= $top_op_info['precedence'] ) ||
		       ( $current_op_info['associativity'] === 'R' &&
		         $current_op_info['precedence'] < $top_op_info['precedence'] );
	}

	/**
	 * Processes a right parenthesis in the expression
	 *
	 * @param array $stack  Operator stack
	 * @param array $output Output queue
	 *
	 * @throws Exception If parentheses are mismatched
	 */
	private function process_right_parenthesis( array &$stack, array &$output ): void {
		$found_left_parens = false;
		while ( ! empty( $stack ) ) {
			$operator = array_pop( $stack );
			if ( $operator === '(' ) {
				$found_left_parens = true;
				break;
			}
			$output[] = $operator;
		}

		if ( ! $found_left_parens ) {
			throw new Exception( "Mismatched parentheses" );
		}
	}

	/**
	 * Evaluates expression in Reverse Polish Notation (RPN)
	 *
	 * @param array $postfix Expression in postfix notation
	 *
	 * @return string|int|\WP_Error Evaluated result or WP_Error in WordPress environment
	 * @throws Exception In non-WordPress environment if expression contains errors
	 */
	private function evaluate_rpn( array $postfix ) {
		$stack = [];

		foreach ( $postfix as $token ) {
			if ( is_numeric( $token ) ) {
				$stack[] = $token;
				continue;
			}

			if ( ! isset( self::OPERATORS[ $token ] ) ) {
				return $this->handle_error( "Unknown operator: $token", 'unknown_operator' );
			}

			if ( count( $stack ) < 2 ) {
				return $this->handle_error(
					"Insufficient operands for operator: $token",
					'insufficient_operands'
				);
			}

			$right = array_pop( $stack );
			$left  = array_pop( $stack );

			if ( $token === '/' && $right == 0 ) {
				return $this->handle_error( "Division by zero", 'division_by_zero' );
			}

			$function = self::OPERATORS[ $token ]['function'];
			$result   = $function( $left, $right, $this->scale );
			$stack[]  = $result;
		}

		if ( count( $stack ) !== 1 ) {
			return $this->handle_error(
				"Invalid expression: too many operands",
				'invalid_expression'
			);
		}

		return $this->format_result( array_pop( $stack ) );
	}

	/**
	 * Formats the final result
	 *
	 * Removes unnecessary decimal zeros and converts to integer if possible.
	 *
	 * @param string $result Result to format
	 *
	 * @return string|int Formatted result
	 */
	private function format_result( string $result ) {
		// Remove trailing zeros and decimal point if unnecessary
		$result = rtrim( rtrim( $result, '0' ), '.' );

		// Convert to integer if no decimal part
		if ( strpos( $result, '.' ) === false ) {
			return (int) $result;
		}

		return $result;
	}

}