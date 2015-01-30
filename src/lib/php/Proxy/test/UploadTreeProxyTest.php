<?php
/*
Copyright (C) 2015, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Test\TestPgDb;

class UploadTreeProxyTest extends \PHPUnit_Framework_TestCase
{
  private $testDb;

  public function setUp()
  {
    $this->testDb = new TestPgDb();
    $this->testDb->createPlainTables( array('uploadtree') );
    $this->testDb->getDbManager()->queryOnce('ALTER TABLE uploadtree RENAME TO uploadtree_a');
    $this->testDb->insertData(array('uploadtree_a'));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
  }
  
  public function testGetNonArtifactDescendantsWithMaterialize()
  {
    $uploadTreeProxy = new UploadTreeProxy($uploadId=1, $options=array(), $uploadTreeTableName='uploadtree_a');
    $uploadTreeProxy->materialize();
    
    $artifact = new ItemTreeBounds(2,'uploadtree_a', $uploadId, 2, 3);
    $artifactDescendants = $uploadTreeProxy->getNonArtifactDescendants($artifact);
    assertThat($artifactDescendants, emptyArray());
   
    $zip = new ItemTreeBounds(1,'uploadtree_a', $uploadId, 1, 24);
    $zipDescendants = $uploadTreeProxy->getNonArtifactDescendants($zip);
    assertThat(array_keys($zipDescendants), arrayContainingInAnyOrder(array(6,7,8,10,11,12)) );

    $uploadTreeProxy->unmaterialize();
  }
  
  public function testGetNonArtifactDescendantsWithoutMaterialize()
  {
    $uploadTreeProxy = new UploadTreeProxy($uploadId=1, $options=array(), $uploadTreeTableName='uploadtree_a');
    
    $artifact = new ItemTreeBounds(2,'uploadtree_a', $uploadId, 2, 3);
    $artifactDescendants = $uploadTreeProxy->getNonArtifactDescendants($artifact);
    assertThat($artifactDescendants, emptyArray());
   
    $zip = new ItemTreeBounds(1,'uploadtree_a', $uploadId, 1, 24);
    $zipDescendants = $uploadTreeProxy->getNonArtifactDescendants($zip);
    assertThat(array_keys($zipDescendants), arrayContainingInAnyOrder(array(6,7,8,10,11,12)) );
  }


}
 