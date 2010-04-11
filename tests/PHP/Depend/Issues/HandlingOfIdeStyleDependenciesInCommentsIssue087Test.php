<?php
/**
 * This file is part of PHP_Depend.
 *
 * PHP Version 5
 *
 * Copyright (c) 2008-2010, Manuel Pichler <mapi@pdepend.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   PHP
 * @package    PHP_Depend
 * @subpackage Issues
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008-2010 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.pdepend.org/
 */

require_once dirname(__FILE__) . '/AbstractTest.php';

/**
 * Test case for issue #87. Handling of dependencies declared in inline comments.
 *
 * @category   PHP
 * @package    PHP_Depend
 * @subpackage Issues
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008-2010 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.pdepend.org/
 */
class PHP_Depend_Issues_HandlingOfIdeStyleDependenciesInCommentsIssue087Test
    extends PHP_Depend_Issues_AbstractTest
{
    /**
     * Tests that the parser recognizes a inline type definition within a comment.
     * Such a comment will look like:
     *
     * <code>
     * function foo() {
     *     /* @var $bar Bar * /
     *     $bar = bar();
     * }
     * </code>
     *
     * @return void
     * @covers PHP_Depend_Parser::_parseCommentWithOptionalInlineClassOrInterfaceReference
     * @group pdepend
     * @group pdepend::issues
     * @group pdepend::parser
     * @group unittest
     */
    public function testParserSetsDependencyDefinedInInlineCommentWithWhitespace()
    {
        $packages   = self::parseTestCase();
        $function   = $packages->current()->getFunctions()->current();
        $dependency = $function->getDependencies()->current();

        $this->assertSame('Bar', $dependency->getName());
    }

    /**
     * Tests that the parser recognizes a inline type definition within a comment.
     * Such a comment will look like:
     *
     * <code>
     * function foo() {
     *     /*@var $bar Bar* /
     *     $bar = bar();
     * }
     * </code>
     *
     * @return void
     * @covers PHP_Depend_Parser::_parseCommentWithOptionalInlineClassOrInterfaceReference
     * @group pdepend
     * @group pdepend::issues
     * @group pdepend::parser
     * @group unittest
     */
    public function testParserSetsDependencyDefinedInInlineCommentWithoutWhitespace()
    {
        $packages   = self::parseTestCase();
        $function   = $packages->current()->getFunctions()->current();
        $dependency = $function->getDependencies()->current();

        $this->assertSame('Bar', $dependency->getName());
    }

    /**
     * Tests that the parser ignores a inline type definition within a multiline
     * comment.
     * Such a comment will look like:
     *
     * <code>
     * function foo() {
     *     /*
     *      * @var $bar Bar
     *      * /
     *     $bar = bar();
     * }
     * </code>
     *
     * @return void
     * @covers PHP_Depend_Parser::_parseCommentWithOptionalInlineClassOrInterfaceReference
     * @group pdepend
     * @group pdepend::issues
     * @group pdepend::parser
     * @group unittest
     */
    public function testParserIgnoresDependencyDefinedInMultilineComment()
    {
        $packages = self::parseTestCase();
        $function = $packages->current()->getFunctions()->current();

        $dependencies = $function->getDependencies();
        $this->assertSame(0, $dependencies->count());
    }

    /**
     * Tests that the parser ignores a inline type definition within a nested
     * comment.
     * Such a comment will look like:
     *
     * <code>
     * function foo() {
     *     // A comment line... /* @var $bar Bar * /
     *     $bar = bar();
     * }
     * </code>
     *
     * @return void
     * @covers PHP_Depend_Parser::_parseCommentWithOptionalInlineClassOrInterfaceReference
     * @group pdepend
     * @group pdepend::issues
     * @group pdepend::parser
     * @group unittest
     */
    public function testParserIgnoresDependencyDefinedWithinAnotherComment()
    {
        $packages = self::parseTestCase();
        $function = $packages->current()->getFunctions()->current();

        $dependencies = $function->getDependencies();
        $this->assertSame(0, $dependencies->count());
    }
}