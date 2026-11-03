<?php
/*
 * SPDX-FileCopyrightText: © Fossology contributors
 *
 * SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\UI\Api\Controllers\RestController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

class RestControllerTest extends TestCase
{
  /** @dataProvider provideRequestBodies */
  public function testGetParsedBody($method, $contentType, $body, $parsedBody,
    $expected)
  {
    $controller = new class extends RestController {
      public function __construct()
      {
      }

      public function getParsedBody(ServerRequestInterface $request)
      {
        return parent::getParsedBody($request);
      }
    };
    $headers = new Headers();
    $headers->setHeader('Content-Type', $contentType);
    $request = new Request($method, new Uri('http', 'localhost'), $headers,
      [], [], (new StreamFactory())->createStream($body));
    $request = $request->withParsedBody($parsedBody);

    $this->assertSame($expected, $controller->getParsedBody($request));
  }

  public static function provideRequestBodies()
  {
    $form = 'application/x-www-form-urlencoded';
    $cases = [];
    foreach (['POST', 'PUT'] as $method) {
      foreach (['0', '1', '2'] as $permission) {
        $cases[$method . '-permission-' . $permission] = [$method, $form,
          'perm=' . $permission, null, ['perm' => $permission]];
      }
      $cases[$method . '-empty'] = [$method, $form, '', null, []];
      $cases[$method . '-encoded'] = [$method, $form,
        'name=group+name&value=%2B1', null,
        ['name' => 'group name', 'value' => '+1']];
      $cases[$method . '-array'] = [$method, $form, 'perm%5B%5D=1', null,
        ['perm' => ['1']]];
    }
    $cases['form-charset'] = ['PUT', $form . '; charset=UTF-8', 'perm=1', null,
      ['perm' => '1']];
    $cases['form-mixed-case'] = ['PUT',
      'Application/X-WWW-Form-Urlencoded; charset=UTF-8', 'perm=2', null,
      ['perm' => '2']];
    $cases['form-whitespace'] = ['PUT', $form . ' ; charset=UTF-8', 'perm=0',
      null, ['perm' => '0']];
    $cases['parsed-form'] = ['POST', $form, 'perm=0', ['perm' => '2'],
      ['perm' => '2']];
    $cases['parsed-empty-form'] = ['PUT', $form, 'perm=1', [], []];
    $object = (object) ['perm' => '2'];
    $cases['parsed-object'] = ['PUT', $form, 'perm=0', $object, $object];
    $cases['json'] = ['PUT', 'application/json', '{"perm":1}', null,
      ['perm' => 1]];
    $cases['json-mixed-case'] = ['POST', 'Application/JSON', '{"perm":0}', null,
      ['perm' => 0]];
    $cases['json-raw-precedence'] = ['PUT', 'application/json', '{"perm":2}',
      ['perm' => 0], ['perm' => 2]];
    $cases['json-invalid'] = ['PUT', 'application/json', '{', null, null];
    $cases['multipart'] = ['POST', 'multipart/form-data; boundary=test',
      'perm=1', ['perm' => '2'], ['perm' => '2']];
    $cases['multipart-unparsed'] = ['PUT', 'multipart/form-data; boundary=test',
      'perm=1', null, null];
    $cases['text'] = ['PUT', 'text/plain', 'perm=1', null, null];
    $cases['missing-content-type'] = ['PUT', '', 'perm=1', null, null];
    return $cases;
  }
}
