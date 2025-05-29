/*
 SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

#include "licenseExpression.hpp"

/**
 * Constructor for License Expression Parser
 * @param exp License Expression to be parsed
 */
LicenseExpressionParser::LicenseExpressionParser(const string& expr) : expression(expr), pos(0) {}

/**
 * Parse the License Expression
 * @return bool based on if the License Expression is valid or not
 */
bool LicenseExpressionParser::parse() {
    root = nullptr;
    stack<string> operators;
    stack<unique_ptr<Node>> operands;

    while (pos < expression.length()) {
        skipWhitespace();
        if (isalpha(peek())) {
            if (isOperatorAtCurrentPos()) {
                string op = parseOperator();
                while (!operators.empty() && operators.top() != "(" && getPrecedence(operators.top()) >= getPrecedence(op)) {
                    if (!applyOperator(operators, operands)) return false;
                }
                operators.push(op);
            } else {
                unique_ptr<Node> licenseNode;
                if (!parseLicense(licenseNode)) return false;
                operands.push(unique_ptr<Node>(move(licenseNode)));
            }
        } else if (peek() == '(') {
            operators.push(string(1, peek()));
            advance();
        } else if (peek() == ')') {
            while (!operators.empty() && operators.top() != "(") {
                if (!applyOperator(operators, operands)) return false;
            }
            if (operators.empty()) return false; // Mismatched parentheses
            operators.pop(); // Pop the '('
            advance();
        } else {
            return false; // Invalid character
        }
    }

    while (!operators.empty()) {
        if (!applyOperator(operators, operands)) return false;
    }

    if (operands.size() != 1) return false;
    root = move(operands.top());
    return true;
}

/**
 * Converts the parsed license expression to AST
 * @return AST in json format
 */
Value LicenseExpressionParser::getAST() {
    if (root) {
        return nodeToJson(*root);
    }
    
    // If root is null, return an AST with all license names combined with "AND"
    if (!licenses.empty()) {
        unique_ptr<Node> newRoot(new Node("Expression", "AND"));
        Node* current = newRoot.get();
        
        auto it = licenses.begin();
        current->left.reset(new Node("License", *it)); // Using reset to initialize unique_ptr
        ++it;
        
        while (it != licenses.end()) {
            current->right.reset(new Node("License", *it)); // Using reset to initialize unique_ptr
            if (next(it) != licenses.end()) {
                current->left.reset(new Node("Expression", "AND")); // Using reset to initialize unique_ptr
                current = static_cast<Node*>(current->left.get());
            }
            ++it;
        }
        
        root = move(newRoot);
        return nodeToJson(*root);
    }

    return Value::null;
}

/**
 * Checks if the new expression is contained in the old expression and combine them
 * @return bool
 */
bool LicenseExpressionParser::containsExpression(const string& newExpr) {
    LicenseExpressionParser newParser(newExpr);
    if (!newParser.parse()) {
        unordered_set<string> newLicenses = newParser.licenses;
        bool contains = true;
        for (const auto& license : newLicenses) {
            if (licenses.find(license) == licenses.end()) {
                // License not found in current expression, update AST
                unique_ptr<Node> newRoot(new Node("Expression", "AND"));
                newRoot->left = move(root);
                newRoot->right.reset(new Node("License", license)); // Using reset to initialize unique_ptr
                root = move(newRoot);
                contains = false;
            }
        }
        return contains;
    }

    if (isContained(root, newParser.root)) {
        return true;
    } else {
        // Update AST with the new expression
        unique_ptr<Node> newRoot(new Node("Expression", "AND"));
        newRoot->left = move(root);
        newRoot->right = move(newParser.root);
        root = move(newRoot);
        return false;
    }
}

/**
 * Checks if valid license
 * @param license
 * @return bool
 */
bool LicenseExpressionParser::isValidLicense(const string& license) {
    return (!isOperator(license)) && license.size() > 1;
}

/**
 * Checks if valid operator
 * @param token
 * @return bool
 */
bool LicenseExpressionParser::isOperator(const string& token) {
    return token == "OR" || token == "AND" || token == "WITH";
}

/**
 * Get Precedence of operator
 * @param op Operator
 * @return bool
 */
int LicenseExpressionParser::getPrecedence(const string& op) {
    if (op == "WITH") return 3;
    if (op == "AND") return 2;
    if (op == "OR") return 1;
    return 0;
}

/**
 * Checks if 2nd expression contained in 1st expression
 * @param root1 root of 1st expression
 * @param root2 root of 2nd expression
 * @return bool
 */
bool LicenseExpressionParser::isContained(const unique_ptr<Node>& root1, const unique_ptr<Node>& root2) {
        if (!root2) return true; // Empty expression is always contained
        if (!root1) return false;

        if (root1->type == "License" && root2->type == "License") {
            return root1->value == root2->value;
        }

        if (root1->type == "Expression" && root2->type == "Expression" && root1->value == root2->value) {
            return (isContained(root1->left, root2->left) && isContained(root1->right, root2->right)) ||
                   (isContained(root1->left, root2->right) && isContained(root1->right, root2->left)) ||
                   isContained(root1->left, root2) || isContained(root1->right, root2);
        }

        // Recursively check if root2 is contained in any subtree of root1
        return isContained(root1->left, root2) || isContained(root1->right, root2);
    }

/**
 * Parses License at the current position and creates a node
 * @param node
 * @return bool
 */
bool LicenseExpressionParser::parseLicense(unique_ptr<Node>& node) {
    skipWhitespace();
    size_t start = pos;
    while (isalnum(peek()) || peek() == '-' || peek() == '.') {
        advance();
    }
    string license = expression.substr(start, pos - start);
    if (isValidLicense(license)) {
        node.reset(new Node("License", license));
        licenses.insert(license);
        return true;
    }
    return false;
}

/**
 * Parses operator at the current position
 * @return operator
 */
string LicenseExpressionParser::parseOperator() {
    skipWhitespace();
    if (boost::iequals(expression.substr(pos, 2), "OR")) {
        pos += 2;
        return "OR";
    } else if (boost::iequals(expression.substr(pos, 3), "AND")) {
        pos += 3;
        return "AND";
    } else if (boost::iequals(expression.substr(pos, 4), "WITH")) {
        pos += 4;
        return "WITH";
    }
    return "";
}

/**
 * Checks if operator at current position
 * @return bool
 */
bool LicenseExpressionParser::isOperatorAtCurrentPos() {
    return boost::iequals(expression.substr(pos, 2), "OR") ||
           boost::iequals(expression.substr(pos, 3), "AND") ||
           boost::iequals(expression.substr(pos, 4), "WITH");
}

/**
 * Changes position to the next character that is not white space
 */
void LicenseExpressionParser::skipWhitespace() {
    while (pos < expression.length() && isspace(expression[pos])) {
        pos++;
    }
}

/**
 * @return character at current position
 */
char LicenseExpressionParser::peek() {
    return pos < expression.length() ? expression[pos] : '\0';
}

/**
 * Increases position by one
 */
void LicenseExpressionParser::advance() {
    if (pos < expression.length()) pos++;
}

/**
 * Creates expression from two operands and a operator and push into operands stack
 * @param operators Stack
 * @param operand Stack
 * @return bool
 */
bool LicenseExpressionParser::applyOperator(stack<string>& operators, stack<unique_ptr<Node>>& operands) {
    if (operands.size() < 2) return false;
    string op = operators.top();
    operators.pop();

    unique_ptr<Node> right = move(operands.top());
    operands.pop();
    unique_ptr<Node> left = move(operands.top());
    operands.pop();

    unique_ptr<Node> newNode(new Node("Expression", op));
    newNode->left = move(left);
    newNode->right = move(right);

    operands.push(move(newNode));
    return true;
}

/**
 * Convert the provided node to json
 * @param node
 * @return json
 */
Value LicenseExpressionParser::nodeToJson(const Node& node) const {
    Value j;
    j["type"] = node.type;
    j["value"] = node.value;
    if (node.left) {
        j["left"] = nodeToJson(*node.left);
    }
    if (node.right) {
        j["right"] = nodeToJson(*node.right);
    }
    return j;
}
