<?php
	/**
	 * Class: QueryBuilder
	 * A generic SQL query builder. All methods are static so there's no point in instantiating.
	 */
	class QueryBuilder {
		/**
		 * Function: build_insert
		 * Creates a full insert query.
		 */
		public static function build_insert($table, $data, &$params = array()) {
			$conditions = self::build_conditions($data, $params);
			$data = array();

			foreach ($conditions as $cond) {
				$split = explode(" = ", $cond);
				$data[$split[0]] = $split[1];
			}

			return "INSERT INTO __$table\n".
                   self::build_insert_header($data)."\n".
                   "VALUES\n".
                   self::build_insert_values($data);
		}

		/**
		 * Function: build_replace
		 * Creates a full replace query.
		 */
		public static function build_replace($table, $data) {
			$conditions = self::build_conditions($data, $params);
			$data = array();

			foreach ($conditions as $cond) {
				$split = explode(" = ", $cond);
				$data[$split[0]] = $split[1];
			}

			return "REPLACE INTO __$table\n".
                   self::build_insert_header($data)."\n".
                   "VALUES\n".
                   self::build_insert_values($data);
		}

		/**
		 * Function: build_update
		 * Creates a full update query.
		 */
		public static function build_update($table, $conds, $data, &$params = array()) {
			return "UPDATE __$table\n".
                   "SET ".self::build_update_values($data, $params)."\n".
                   ($conds ? "WHERE ".self::build_where($conds, $table, $params) : "");
		}

		/**
		 * Function: build_delete
		 * Creates a full delete query.
		 */
		public static function build_delete($table, $conds, &$params = array()) {
			return "DELETE FROM __$table\n".
                   ($conds ? "WHERE ".self::build_where($conds, $table, $params) : "");
		}

		/**
		 * Function: build_update_values
		 * Creates an update data part.
		 */
		public static function build_update_values($data, &$params = array()) {
			$set = self::build_conditions($data, $params);
			return implode(", ", $set);
		}

		/**
		 * Function: build_insert_header
		 * Creates an insert header part.
		 */
		public static function build_insert_header($data) {
			$set = array();

			foreach (array_keys($data) as $field)
				array_push($set, $field);

			return "(".implode(", ", $set).")";
		}

		/**
		 * Function: build_insert_values
		 * Creates an insert data part.
		 */
		public static function build_insert_values($data) {
			return "(".implode(', ', array_values($data)).")";
		}

		/**
		 * Function: build_limits
		 * Creates a LIMIT part for a query.
		 */
		public static function build_limits($offset, $limit) {
			if ($limit === null)
				return "";

			if ($offset !== null)
				return "LIMIT ".$offset.", ".$limit;

			return "LIMIT ".$limit;
		}

		/**
		 * Function: build_from
		 * Creates a FROM header for select queries.
		 */
		public static function build_from($tables) {
			if (!is_array($tables))
				$tables = array($tables);

			foreach ($tables as &$table)
				if (substr($table, 0, 2) != "__")
					$table = "__".$table;

			return implode(", ", $tables);
		}

		/**
		 * Function: build_count
		 * Creates a SELECT COUNT(1) query.
		 */
		public static function build_count($tables, $conds, &$params = array()) {
			return "SELECT COUNT(1) AS count\n".
			       "FROM ".self::build_from($tables)."\n".
			       ($conds ? "WHERE ".self::build_where($conds, $tables, $params) : "");
		}

		/**
		 * Function: build_select_header
		 * Creates a SELECT fields header.
		 */
		public static function build_select_header($fields, $tables = null) {
			if (!is_array($fields))
				$fields = array($fields);

			$tables = (array) $tables;

			foreach ($fields as &$field)
				self::tablefy($field, $tables);

			return implode(', ', $fields);
		}

		/**
		 * Function: build_where
		 * Creates a WHERE query.
		 */
		public static function build_where($conds, $tables = null, &$params = array()) {
			$conds = (array) $conds;
			$tables = (array) $tables;

			$conditions = self::build_conditions($conds, $params, $tables);

			return (empty($conditions)) ? "" : "(".implode(") AND (", array_filter($conditions)).")";
		}

		/**
		 * Function: build_group
		 * Creates a GROUP BY argument.
		 */
		public static function build_group($by, $tables = null) {
			$by = (array) $by;
			$tables = (array) $tables;

			foreach ($by as &$column)
				self::tablefy($column, $tables);

			return implode(", ", array_unique(array_filter($by)));
		}

		/**
		 * Function: build_order
		 * Creates a ORDER BY argument.
		 */
		public static function build_order($order, $tables = null) {
			$tables = (array) $tables;

			if (!is_array($order))
				$order = explode(", ", $order);

			foreach ($order as &$by)
				self::tablefy($by, $tables);

			return implode(", ", $order);
		}

		public static function build_conditions($conds, &$params, $tables = null) {
			foreach ($conds as $key => $val) {
				if (is_numeric($key)) # Full expression
					$cond = $val;
				else { # Key => Val expression
					if (is_string($val) and $val[0] == ":")
						$cond = $key." = ".$val;
					else {
						if (substr($key, -4) == " not") { # Negation
							$key = substr($key, 0, -4);
							if (is_array($val))
								$cond = $key." NOT IN ".self::build_in($val);
							elseif ($val === null)
								$cond = $key." IS NOT NULL";
							else {
								$cond = $key." != :".$key;
								$params[":".$key] = $val;
							}
						} elseif (substr($key, -5) == " like") { # LIKE
							$key = substr($key, 0, -5);
							$cond = $key." LIKE :".$key;
							$params[":".$key] = $val;
						} elseif (substr($key, -9) == " not like") { # NOT LIKE
							$key = substr($key, 0, -9);
							$cond = $key." NOT LIKE :".$key;
							$params[":".$key] = $val;
						} else { # Equation
							if (is_array($val))
								$cond = $key." IN ".self::build_in($val);
							elseif ($val === null)
								$cond = $key." IS NULL";
							else {
								$cond = $key." = :".$key;
								$params[":".$key] = $val;
							}
						}
					}
				}

				if ($tables)
					self::tablefy($cond, $tables);

				$conditions[] = $cond;
			}

			return $conditions;
		}

		public static function build_in($vals) {
			$return = array();

			foreach ($vals as $val)
				$return[] = SQL::current()->escape($val);

			return "(".join(",", $return).")";
		}

		/**
		 * Function: build_select
		 * Creates a full SELECT query.
		 */
		public static function build_select($tables, $fields, $conds, $order = null, $limit = null, $offset = null, $group = null, $left_join = array(), &$params = array()) {
			$query = "SELECT ".self::build_select_header($fields, $tables)."\n".
			         "FROM ".self::build_from($tables)."\n";

			foreach ($left_join as $join)
				$query.= "LEFT JOIN __".$join["table"]." ON ".self::build_where($join["where"], $join["table"], $params)."\n";

			$query.= ($conds ? "WHERE ".self::build_where($conds, $tables, $params) : "")."\n".
			         ($group ? "GROUP BY ".self::build_group($group, $tables) : "")."\n".
			         ($order ? "ORDER BY ".self::build_order($order, $tables) : "")."\n".
			         self::build_limits($offset, $limit);

			return $query;
		}

		/**
		 * Function: tablefy
		 * Automatically prepends tables and table prefixes to a field if it doesn't already have them.
		 *
		 * Parameters:
		 *     $field - The field to "tablefy".
		 *     $tables - An array of tables. The first one will be used for prepending.
		 */
		public static function tablefy(&$field, $tables) {
			if (!preg_match_all("/(\(|[\s]+|^)(?!__)([a-z0-9_\.\*]+)(\)|[\s]+|$)/", $field, $matches))
				return;

			foreach ($matches[0] as $index => $full) {
				$before = $matches[1][$index];
				$name   = $matches[2][$index];
				$after  = $matches[3][$index];

				if (is_numeric($name))
					continue;

				# Does it not already have a table specified?
				if (!substr_count($full, ".")) {
						                   # Don't replace things that are already either prefixed or paramized.
					$field = preg_replace("/([^\.:'\"_]|^)".preg_quote($full, "/")."/",
					                      "\\1".$before."__".$tables[0].".".$name.$after,
					                      $field,
					                      1);
				} else {
					# Okay, it does, but is the table prefixed?
					if (substr($full, 0, 2) != "__") {
						                       # Don't replace things that are already either prefixed or paramized.
						$field = preg_replace("/([^\.:'\"_]|^)".preg_quote($full, "/")."/",
						                      "\\1".$before."__".$name.$after,
						                      $field,
						                      1);
					}
				}
			}

			$field = preg_replace("/AS ([^ ]+)\./i", "AS ", $field);
		}
	}
