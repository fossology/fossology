/*
 SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

#include "licenseExpression.hpp"

LicenseExpressionParser::LicenseExpressionParser(const string& expr) : expression(expr), pos(0) {}

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


bool LicenseExpressionParser::isValidLicense(const string& license) {
    return (!isOperator(license)) && license.size() > 1;
}

bool LicenseExpressionParser::isOperator(const string& token) {
    return token == "OR" || token == "AND" || token == "WITH";
}

int LicenseExpressionParser::getPrecedence(const string& op) {
    if (op == "WITH") return 3;
    if (op == "AND") return 2;
    if (op == "OR") return 1;
    return 0;
}

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

bool LicenseExpressionParser::isOperatorAtCurrentPos() {
    return boost::iequals(expression.substr(pos, 2), "OR") ||
           boost::iequals(expression.substr(pos, 3), "AND") ||
           boost::iequals(expression.substr(pos, 4), "WITH");
}

void LicenseExpressionParser::skipWhitespace() {
    while (pos < expression.length() && isspace(expression[pos])) {
        pos++;
    }
}

char LicenseExpressionParser::peek() {
    return pos < expression.length() ? expression[pos] : '\0';
}

void LicenseExpressionParser::advance() {
    if (pos < expression.length()) pos++;
}

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
