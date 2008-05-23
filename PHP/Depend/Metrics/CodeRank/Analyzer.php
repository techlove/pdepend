<?php
/**
 * This file is part of PHP_Depend.
 * 
 * PHP Version 5
 *
 * Copyright (c) 2008, Manuel Pichler <mapi@pmanuel-pichler.de>.
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
 * @category  QualityAssurance
 * @package   PHP_Depend
 * @author    Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright 2008 Manuel Pichler. All rights reserved.
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   SVN: $Id$
 * @link      http://www.manuel-pichler.de/
 */

require_once 'PHP/Depend/Code/NodeVisitor.php';
require_once 'PHP/Depend/Metrics/AnalyzerI.php';
require_once 'PHP/Depend/Metrics/ResultSetI.php';;
require_once 'PHP/Depend/Metrics/ResultSet/NodeAwareI.php';

/**
 * Calculates the code ranke metric for classes and packages. 
 *
 * @category  QualityAssurance
 * @package   PHP_Depend
 * @author    Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright 2008 Manuel Pichler. All rights reserved.
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   Release: @package_version@
 * @link      http://www.manuel-pichler.de/
 */
class PHP_Depend_Metrics_CodeRank_Analyzer 
    implements PHP_Depend_Code_NodeVisitor,
               PHP_Depend_Metrics_AnalyzerI,
               PHP_Depend_Metrics_ResultSetI,
               PHP_Depend_Metrics_ResultSet_NodeAwareI
{
    /**
     * The used damping factor.
     */
    const DAMPING_FACTOR = 0.85;
    
    /**
     * The found class nodes.
     *
     * @type array
     * @var array $classNodes
     */
    protected $classNodes = array();
    
    /**
     * The calculated class ranks.
     *
     * @type Iterator
     * @var Iterator $classRank
     */
    protected $classRank = null;
    
    /**
     * The found package nodes.
     *
     * @type Iterator
     * @var Iterator $packageNodes
     */
    protected $packageNodes = array();
    
    /**
     * The calculated package ranks.
     *
     * @type array<PHP_Depend_Metrics_CodeRank_Package>
     * @var array(PHP_Depend_Metrics_CodeRank_Package) $packageRank
     */
    protected $packageRank = null;
    
    /**
     * All found nodes.
     *
     * @type array<array>
     * @var array(string=>array) $nodes
     */
    protected $nodes = array();
    
    /**
     * Hash with all calculated node metrics.
     *
     * <code>
     * array(
     *     '0375e305-885a-4e91-8b5c-e25bda005438'  =>  array(
     *         'loc'    =>  42,
     *         'ncloc'  =>  17,
     *         'cc'     =>  12
     *     ),
     *     'e60c22f0-1a63-4c40-893e-ed3b35b84d0b'  =>  array(
     *         'loc'    =>  42,
     *         'ncloc'  =>  17,
     *         'cc'     =>  12
     *     )
     * )
     * </code>
     *
     * @type array<array>
     * @var array(string=>array) $nodeMetrics
     */
    protected $nodeMetrics = null;
    
    /**
     * Processes all {@link PHP_Depend_Code_Package} code nodes.
     *
     * @param PHP_Depend_Code_NodeIterator $packages All code packages.
     * 
     * @return PHP_Depend_Metrics_ResultSetI
     */
    public function analyze(PHP_Depend_Code_NodeIterator $packages)
    {
        // First traverse package tree
        foreach ($packages as $package) {
            $package->accept($this);
        }
        
        // Calculate code rank metrics
        $this->buildCodeRankMetrics();
        
        return $this;
    }
    
    /**
     * This method returns an <b>array</b> with all aggregated metrics.
     * 
     * @return array(string=>array)
     * @see PHP_Depend_Metrics_ResultSet_NodeAwareI::getAllNodeMetrics()
     */
    public function getAllNodeMetrics()
    {
        return $this->nodeMetrics;
    }
    
    /**
     * This method will return an <b>array</b> with all generated metric values 
     * for the node with the given <b>$uuid</b> identifier. If there are no
     * metrics for the requested node, this method will return an empty <b>array</b>.
     *
     * @param string $uuid The unique node identifier.
     * 
     * @return array(string=>mixed)
     */
    public function getNodeMetrics($uuid)
    {
        if (isset($this->nodeMetrics[$uuid])) {
            return $this->nodeMetrics[$uuid];
        }
        return array();
    }
    
    /**
     * Visits a code class object.
     *
     * @param PHP_Depend_Code_Class $class The context code class.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor::visitClass()
     * @see PHP_Depend_Metrics_CodeRank_Analyzer::visitType()
     */
    public function visitClass(PHP_Depend_Code_Class $class)
    {
        $this->visitType($class);
    
        foreach ($class->getMethods() as $method) {
            $method->accept($this);
        }
        foreach ($class->getProperties() as $property) {
            $property->accept($this);
        }
    }
    
    /**
     * Visits a code interface object.
     *
     * @param PHP_Depend_Code_Interface $interface The context code interface.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor::visitInterface()
     * @see PHP_Depend_Metrics_CodeRank_Analyzer::visitType()
     */
    public function visitInterface(PHP_Depend_Code_Interface $interface)
    {
        $this->visitType($interface);
    
        foreach ($interface->getMethods() as $method) {
            $method->accept($this);
        }
    }
    
    /**
     * Visits a code function object.
     *
     * @param PHP_Depend_Code_Function $function The context code function.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor::visitFunction()
     */
    public function visitFunction(PHP_Depend_Code_Function $function)
    {

    }
    
    /**
     * Visits a code method object.
     *
     * @param PHP_Depend_Code_Method $method The context code method.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor::visitMethod()
     */
    public function visitMethod(PHP_Depend_Code_Method $method)
    {

    }
    
    /**
     * Visits a code package object.
     *
     * @param PHP_Depend_Code_Package $package The context code package.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor::visitPackage()
     */
    public function visitPackage(PHP_Depend_Code_Package $package)
    {
        $this->initNode($package);
        
        foreach ($package->getTypes() as $type) {
            $type->accept($this);
        }
        foreach ($package->getFunctions() as $function) {
            $function->accept($this);
        }
    }
    
    /**
     * Visits a property node. 
     *
     * @param PHP_Depend_Code_Property $property The property class node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitor::visitProperty()
     */
    public function visitProperty(PHP_Depend_Code_Property $property)
    {
        
    }
    
    /**
     * Generic visitor method for classes and interfaces. Both visit methods
     * delegate calls to this method.
     *
     * @param PHP_Depend_Code_AbstractType $type The context type instance.
     * 
     * @return void
     */
    protected function visitType(PHP_Depend_Code_AbstractType $type)
    {
        $pkg = $type->getPackage();
        
        $this->initNode($type);
        
        foreach ($type->getDependencies() as $dep) {
            
            $depPkg = $dep->getPackage();
            
            $this->initNode($dep);
            $this->initNode($depPkg);
            
            $this->nodes[$type->getUUID()]['in'][] = $dep->getUUID();
            $this->nodes[$dep->getUUID()]['out'][] = $type->getUUID();
            
            // No self references
            if ($pkg !== $depPkg) {
                $this->nodes[$pkg->getUUID()]['in'][]     = $depPkg->getUUID();
                $this->nodes[$depPkg->getUUID()]['out'][] = $pkg->getUUID();
            }
        }
    }
    
    /**
     * Initializes the temporary node container for the given <b>$node</b>.
     *
     * @param PHP_Depend_Code_Node $node The context node instance.
     * 
     * @return void
     */
    protected function initNode(PHP_Depend_Code_Node $node)
    {
        if (!isset($this->nodes[$node->getUUID()])) {
            $this->nodes[$node->getUUID()] = array(
                'in'   =>  array(),
                'out'  =>  array()
            );
        }
    }
    
    /**
     * Generates the forward and reverse code rank for the given <b>$nodes</b>.
     *
     * @param array  $nodes List of nodes.
     * @param string $class The metric model class.
     * 
     * @return void
     */
    protected function buildCodeRankMetrics()
    {
        if (is_array($this->nodeMetrics)) {
            return;
        }
        
        $this->nodeMetrics = array();
        
        foreach ($this->nodes as $uuid => $info) {
            $this->nodeMetrics[$uuid] = array('cr'  =>  0, 'rcr'  =>  0);
        }
        foreach ($this->computeCodeRank($this->nodes, 'out', 'in') as $uuid => $rank) {
            $this->nodeMetrics[$uuid]['cr'] = $rank;
        }
        foreach ($this->computeCodeRank($this->nodes, 'in', 'out') as $uuid => $rank) {
            $this->nodeMetrics[$uuid]['rcr'] = $rank;
        }
    }
    
    /**
     * Sorts the given <b>$nodes</b> set.
     *
     * @param array  $nodes List of nodes.
     * @param string $dir1  Identifier for the incoming edges.
     * @param string $dir2  Identifier for the outgoing edges.
     * 
     * @return array
     */
    protected function topologicalSort(array $nodes, $dir1, $dir2)
    {
        $leafs  = array();
        $sorted = array();
        
        // Collect all leaf nodes
        foreach ($nodes as $name => $node) {
            if (count($node[$dir1]) === 0) {
                unset($nodes[$name]);
                $leafs[$name] = $node;
            }
        }

        while (($leaf = reset($leafs)) !== false) {
            $name = key($leafs);
            
            $sorted[$name] = $leaf;
            
            unset($leafs[$name]);
            
            foreach ($leaf[$dir2] as $refName) {
                
                // Search edge index
                $index = array_search($name, $nodes[$refName][$dir1]);
                
                // Remove one edge between these two nodes 
                unset($nodes[$refName][$dir1][$index]);
                
                // If the referenced node has no incoming/outgoing edges,
                // put it in the list of leaf nodes.
                if (count($nodes[$refName][$dir1]) === 0) {
                    $leafs[$refName] = $nodes[$refName];
                    // Remove node from all nodes
                    unset($nodes[$refName]);
                }
            }
        }
        
        if (count($nodes) > 0) {
            throw new RuntimeException('The object structure contains cycles');
        }
        
        return array_keys($sorted);
    }
    
    /**
     * Calculates the code rank for the given <b>$nodes</b> set.
     *
     * @param array  $nodes List of nodes. 
     * @param string $id1   Identifier for the incoming edges.
     * @param string $id2   Identifier for the outgoing edges.
     * 
     * @return array(string=>float)
     */
    protected function computeCodeRank(array $nodes, $id1, $id2)
    {
        $d = self::DAMPING_FACTOR;
        
        $ranks = array();
        foreach ($this->topologicalSort($nodes, $id1, $id2) as $name) {
            $rank = 0.0;
            foreach ($nodes[$name][$id1] as $refName) {
                $diff = 1;
                if (($count = count($nodes[$refName][$id2])) > 0) {
                    $diff = $count;
                }
                $rank += ($ranks[$refName] / $diff);
            }
            
            $ranks[$name] = (1 - $d) + $d * $rank;
        }
        return $ranks;
    }
}