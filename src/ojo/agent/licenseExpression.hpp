/*
 SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef LICENSE_EXPRESSION_HPP
#define LICENSE_EXPRESSION_HPP

#include <iostream>
#include <stack>
#include <memory>
#include <jsoncpp/json/json.h>
#include <boost/algorithm/string.hpp>
#include <unordered_set>

using namespace std;
using Json::Value;

class LicenseExpressionParser {
public:
    LicenseExpressionParser(const string& expr);
    bool parse();
    Value getAST();
    bool containsExpression(const string& newExpr);

private:
    string expression;
    size_t pos;
    struct Node {
        string type;
        string value;
        unique_ptr<Node> left;
        unique_ptr<Node> right;

        Node(const string& t, const string& v) : type(t), value(v), left(nullptr), right(nullptr) {}
    };
    unique_ptr<Node> root;
    unordered_set<string> licenses;

    bool parseLicense(unique_ptr<Node>& node);
    string parseOperator();
    bool isOperatorAtCurrentPos();
    void skipWhitespace();
    char peek();
    void advance();
    bool applyOperator(stack<string>& operators, stack<unique_ptr<Node>>& operands);
    Value nodeToJson(const Node& node) const;

    bool isValidLicense(const string& license);
    bool isOperator(const string& token);
    int getPrecedence(const string& op);
    bool isContained(const unique_ptr<Node>& root1, const unique_ptr<Node>& root2);

};

#endif // LICENSE_EXPRESSION_PARSER_HPP
