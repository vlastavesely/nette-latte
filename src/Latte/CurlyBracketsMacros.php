<?php

/**
 * Nette Framework
 *
 * Copyright (c) 2004, 2009 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://nettephp.com
 *
 * @copyright  Copyright (c) 2004, 2009 David Grudl
 * @license    http://nettephp.com/license  Nette license
 * @link       http://nettephp.com
 * @category   Nette
 * @package    Nette\Templates
 * @version    $Id$
 */

/*namespace Nette\Templates;*/



require_once dirname(__FILE__) . '/../../Object.php';



/**
 * Default macros for filter CurlyBracketsFilter.
 *
 * - {$variable} with escaping
 * - {!$variable} without escaping
 * - {*comment*} will be removed
 * - {=expression} echo with escaping
 * - {!=expression} echo without escaping
 * - {?expression} evaluate PHP statement
 * - {_expression} echo translation with escaping
 * - {!_expression} echo translation without escaping
 * - {link destination ...} control link
 * - {plink destination ...} presenter link
 * - {if ?} ... {elseif ?} ... {else} ... {/if} // or <%else%>, <%/if%>, <%/foreach%> ?
 * - {for ?} ... {/for}
 * - {foreach ?} ... {/foreach}
 * - {include ?}
 * - {cache ?} ... {/cache} cached block
 * - {snippet ?} ... {/snippet ?} control snippet
 * - {attr ?} HTML element attributes
 * - {block|texy} ... {/block} capture of filter block
 * - {contentType ...} HTTP Content-Type header
 * - {assign $var value} set template parameter
 * - {dump $var}
 * - {debugbreak}
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2009 David Grudl
 * @package    Nette\Templates
 */
class CurlyBracketsMacros extends /*Nette\*/Object
{
	/** @var array */
	public static $defaultMacros = array(
		'block' => '<?php %:macroBlock% ?>',
		'/block' => '<?php %:macroBlockEnd% ?>',

		'snippet' => '<?php } if ($_cb->foo = SnippetHelper::create($control%:macroSnippet%)) { $_cb->snippets[] = $_cb->foo; ?>',
		'/snippet' => '<?php array_pop($_cb->snippets)->finish(); } if (SnippetHelper::$outputAllowed) { ?>',

		'cache' => '<?php if ($_cb->foo = CachingHelper::create($_cb->key = md5(__FILE__) . __LINE__, $template->getFile(), array(%%))) { $_cb->caches[] = $_cb->foo; ?>',
		'/cache' => '<?php array_pop($_cb->caches)->save(); } if (!empty($_cb->caches)) end($_cb->caches)->addItem($_cb->key); ?>',

		'if' => '<?php if (%%): ?>',
		'elseif' => '<?php elseif (%%): ?>',
		'else' => '<?php else: ?>',
		'/if' => '<?php endif ?>',
		'foreach' => '<?php foreach (%:macroForeach%): ?>',
		'/foreach' => '<?php endforeach; array_pop($_cb->its); $iterator = end($_cb->its) ?>',
		'for' => '<?php for (%%): ?>',
		'/for' => '<?php endfor ?>',
		'while' => '<?php while (%%): ?>',
		'/while' => '<?php endwhile ?>',
		'continueIf' => '<?php if (%%) continue ?>',
		'breakIf' => '<?php if (%%) break ?>',

		'include' => '<?php %:macroInclude% ?>',
		'extends' => '<?php %:macroExtends% ?>',

		'plink' => '<?php echo %:macroEscape%(%:macroPlink%) ?>',
		'link' => '<?php echo %:macroEscape%(%:macroLink%) ?>',
		'ifCurrent' => '<?php %:macroIfCurrent%; if ($presenter->getLastCreatedRequestFlag("current")): ?>',
		'widget' => '<?php %:macroWidget% ?>',

		'attr' => '<?php echo Html::el(NULL)->%:macroAttr%attributes() ?>',
		'contentType' => '<?php %:macroContentType% ?>',
		'assign' => '<?php %:macroAssign% ?>', // deprecated?
		'dump' => '<?php Debug::consoleDump(%:macroDump%, "Template " . str_replace(Environment::getVariable("templatesDir"), "\xE2\x80\xA6", $template->getFile())) ?>',
		'debugbreak' => '<?php if (function_exists("debugbreak")) debugbreak() ?>',

		'!_' => '<?php echo $template->translate(%:macroModifiers%) ?>',
		'!=' => '<?php echo %:macroModifiers% ?>',
		'_' => '<?php echo %:macroEscape%($template->translate(%:macroModifiers%)) ?>',
		'=' => '<?php echo %:macroEscape%(%:macroModifiers%) ?>',
		'!$' => '<?php echo %:macroVar% ?>',
		'!' => '<?php echo %:macroVar% ?>', // deprecated
		'$' => '<?php echo %:macroEscape%(%:macroVar%) ?>',
		'?' => '<?php %:macroModifiers% ?>', // deprecated?
	);

	/** @var CurlyBracketsFilter */
	private $filter;

	/** @var array */
	private $current;

	/** @var array */
	private $blocks = array();

	/** @var array */
	private $namedBlocks = array();

	/** @var bool */
	private $extends;



	/**
	 * Initializes parsing.
	 * @param  CurlyBracketsFilter
	 * @param  string
	 * @return void
	 */
	public function initialize($filter, & $s)
	{
		$this->filter = $filter;
		$this->blocks = array();
		$this->namedBlocks = array();
		$this->extends = NULL;

		$filter->context = CurlyBracketsFilter::CONTEXT_TEXT;
		$filter->escape = 'TemplateHelpers::escapeHtml';

		// remove comments
		$s = preg_replace('#\\{\\*.*?\\*\\}[\r\n]*#s', '', $s);

		// snippets support (temporary solution)
		$s = preg_replace(
			'#@(\\{[^}]+?\\})#s',
			'<?php } ?>$1<?php if (SnippetHelper::\\$outputAllowed) { ?>',
			$s
		);
	}



	/**
	 * Finishes parsing.
	 * @param  string
	 * @return void
	 */
	public function finalize(& $s)
	{
		// blocks closing check
		if (count($this->blocks) === 1) { // auto-close last block
			$s .= $this->macro('/block', '', '');

		} elseif ($this->blocks) {
			throw new /*\*/InvalidStateException("There are some unclosed blocks.");
		}

		// snippets support (temporary solution)
		$s = "<?php\nif (SnippetHelper::\$outputAllowed) {\n?>$s<?php\n}\n?>";

		// extends support
		if ($this->namedBlocks || $this->extends) {
			$s = "<?php\n"
				. 'if ($_cb->extends) { ob_start(); }' . "\n"
				. '?>' . $s . "<?php\n"
				. 'if ($_cb->extends) { ob_end_clean(); CurlyBracketsMacros::includeTemplate($_cb->extends, get_defined_vars(), $template)->render(); }' . "\n";
		}

		// named blocks
		if ($this->namedBlocks) {
			foreach (array_reverse($this->namedBlocks, TRUE) as $name => $foo) {
				$name = preg_quote($name, '#');
				$s = preg_replace_callback("#{block($name)} \?>(.*)<\?php {/block$name}#sU", array($this, 'cbNamedBlocks'), $s);
			}
			$s = "<?php\n\n" . implode("\n\n\n", $this->namedBlocks) . "\n\n//\n// end of blocks\n//\n?>" . $s;
		}

		// internal state holder
		$s = "<?php\n"
			/*. 'use Nette\Templates\CurlyBracketsMacros, Nette\Templates\TemplateHelpers, Nette\SmartCachingIterator, Nette\Web\Html, Nette\Templates\SnippetHelper, Nette\Debug, Nette\Environment, Nette\Templates\CachingHelper;' . "\n\n"*/
			. "\$_cb = CurlyBracketsMacros::initRuntime(\$template, " . var_export($this->extends, TRUE) . ", __FILE__); unset(\$_extends);\n"
			. '?>' . $s;
	}



	/**
	 * Process {macro content | modifiers}
	 * @param  string
	 * @param  string
	 * @param  string
	 * @return string
	 */
	public function macro($macro, $content, $modifiers)
	{
		if ($macro === '') {
			$macro = substr($content, 0, 2);
			if (!isset(self::$defaultMacros[$macro])) {
				$macro = substr($content, 0, 1);
				if (!isset(self::$defaultMacros[$macro])) {
					return NULL;
				}
			}
			$content = substr($content, strlen($macro));

		} elseif (!isset(self::$defaultMacros[$macro])) {
			return NULL;
		}
		$this->current = array($content, $modifiers);
		return preg_replace_callback('#%(.*?)%#', array($this, 'cbMacro'), self::$defaultMacros[$macro]);
	}



	/**
	 * Callback for self::macro().
	 */
	private function cbMacro($m)
	{
		list($content, $modifiers) = $this->current;
		if ($m[1]) {
			$callback = $m[1][0] === ':' ? array($this, substr($m[1], 1)) : $m[1];
			/**/fixCallback($callback);/**/
			if (!is_callable($callback)) {
				$able = is_callable($callback, TRUE, $textual);
				throw new /*\*/InvalidStateException("CurlyBrackets macro handler '$textual' is not " . ($able ? 'callable.' : 'valid PHP callback.'));
			}
			return call_user_func($callback, $content, $modifiers);

		} else {
			return $content;
		}
	}



	/**
	 * Process <n:tag n:attr> (experimental).
	 * @param  stdClass
	 * @return string
	 */
	public function tag($tag)
	{
		if ($tag->isSpecial) {
			$knownTags = array(
				'n:include' => 'block',
				'n:for' => 'each',
				'n:block' => 'name',
			);
			$value = isset($knownTags[$tag->name], $tag->attrs['n:' . $knownTags[$tag->name]]) ? $tag->attrs['n:' . $knownTags[$tag->name]] : '';
			if ($tag->name === 'n:block' || $tag->name === 'n:include') $value = '#' . $value;
			return $this->macro(substr($tag->name, 2), $value, isset($tag->attrs['n:modifiers']) ? $tag->attrs['n:modifiers'] : '');

		} else {
			$knownAttrs = array(
				'n:if' => 'if',
				'n:each' => 'foreach',
				'n:block' => 'block',
			);
			$code = $tag->html;
			$attrs = $tag->attrs;
			foreach ($knownAttrs as $name => $macro) {
				if (!isset($attrs[$name])) continue;
				$value = $attrs[$name];
				unset($attrs[$name]);
				if ($name === 'n:block') $value = '#' . $value;
				if ($tag->closing) $macro = '/' . $macro;
				$code = $tag->closing ? $code . $this->macro($macro, $value, '') : $this->macro($macro, $value, '') . $code;
			}
			if ($attrs) {
				throw new /*\*/InvalidStateException("Unknown HTML attribute " . implode(', ', array_keys($attrs)));
			}
			return $code;
		}
	}



	/**
	 * {$var |modifiers}
	 */
	private function macroVar($var, $modifiers)
	{
		return CurlyBracketsFilter::formatModifiers('$' . $var, $modifiers);
	}



	/**
	 * {include ...}
	 */
	private function macroInclude($content, $modifiers)
	{
		$destination = CurlyBracketsFilter::fetchToken($content); // destination [,] [params]
		$params = CurlyBracketsFilter::formatArray($content) . ($content ? ' + ' : '');

		if ($destination === NULL) {
			throw new /*\*/InvalidStateException("Missing destination in {include}.");

		} elseif ($destination[0] === '#') { // include #block
			if (!preg_match('#^\\#'.CurlyBracketsFilter::RE_IDENTIFIER.'$#', $destination)) {
				throw new /*\*/InvalidStateException("Included block name must be alphanumeric string, '$destination' given.");
			}

			$parent = $destination === '#parent';
			if ($destination === '#parent' || $destination === '#this') {
				$item = end($this->blocks);
				while ($item && $item[0][0] !== '#') $item = prev($this->blocks);
				if (!$item) {
					throw new /*\*/InvalidStateException("Cannot include $name block outside of any block.");
				}
				$destination = $item[0];
			}
			$name = var_export($destination, TRUE);
			$params .= 'get_defined_vars()';
			$cmd = isset($this->namedBlocks[$destination]) && !$parent
				? "call_user_func(reset(\$_cb->blocks[$name]), $params)"
				: "CurlyBracketsMacros::callBlock" . ($parent ? 'Parent' : '') . "(\$_cb->blocks, $name, $params)";
			return $modifiers
				? "ob_start(); $cmd; echo " . CurlyBracketsFilter::formatModifiers('ob_get_clean()', $modifiers)
				: $cmd;

		} else { // include "file"
			$destination = CurlyBracketsFilter::formatString($destination);
			$params .= '$template->getParams()';
			return $modifiers
				? 'echo ' . CurlyBracketsFilter::formatModifiers('CurlyBracketsMacros::includeTemplate(' . $destination . ', ' . $params . ', $_cb->templates[__FILE__])->__toString(TRUE)', $modifiers)
				: 'CurlyBracketsMacros::includeTemplate(' . $destination . ', ' . $params . ', $_cb->templates[__FILE__])->render()';
		}
	}



	/**
	 * {extends ...}
	 */
	private function macroExtends($content)
	{
		$destination = CurlyBracketsFilter::fetchToken($content); // destination
		if ($destination === NULL) {
			throw new /*\*/InvalidStateException("Missing destination in {extends}.");
		}
		if (!empty($this->blocks)) {
			throw new /*\*/InvalidStateException("{extends} must be placed outside any block.");
		}
		if ($this->extends !== NULL) {
			throw new /*\*/InvalidStateException("Multiple {extends} declarations are not allowed.");
		}
		$this->extends = $destination !== 'none';
		return $this->extends ? '$_cb->extends = ' . CurlyBracketsFilter::formatString($destination) : '';
	}



	/**
	 * {block ...}
	 */
	private function macroBlock($content, $modifiers)
	{
		$name = CurlyBracketsFilter::fetchToken($content); // block [,] [params]

		if ($name === NULL || $name[0] === '$') { // anonymous block or capture
			$this->blocks[] = array($name, $modifiers);
			return ($name === NULL && $modifiers === '') ? '' : 'ob_start()';

		} elseif ($name[0] === '#') { // #block
			if (!preg_match('#^\\#'.CurlyBracketsFilter::RE_IDENTIFIER.'$#', $name)) {
				throw new /*\*/InvalidStateException("Block name must be alphanumeric string, '$name' given.");

			} elseif (isset($this->namedBlocks[$name])) {
				throw new /*\*/InvalidStateException("Cannot redeclare block '$name'.");
			}

			$top = empty($this->blocks);
			$this->namedBlocks[$name] = $name;
			$this->blocks[] = array($name, '');
			if (!$top) {
				return $this->macroInclude($name, $modifiers) . "{block$name}";

			} elseif ($this->extends) {
				return "{block$name}";

			} else {
				return 'if (!$_cb->extends) { ' . $this->macroInclude($name, $modifiers) . "; } {block$name}";
			}

		} else {
			throw new /*\*/InvalidStateException("Invalid block parameter '$name'.");
		}
	}



	/**
	 * {/block ...}
	 */
	private function macroBlockEnd($content)
	{
		$empty = empty($this->blocks);
		list($name, $modifiers) = array_pop($this->blocks);

		if ($empty || ($content && $content !== $name)) {
			throw new /*\*/InvalidStateException("Tag {/block $content} was not expected here.");

		} elseif (substr($name, 0, 1) === '#') { // #block
			return "{/block$name}";

		} else { // anonymous block or capture
			return ($name === NULL && $modifiers === '') ? ''
				: ($name === NULL ? 'echo ' : $name . '=') . CurlyBracketsFilter::formatModifiers('ob_get_clean()', $modifiers);
		}
	}



	/**
	 * Converts {block#named}...{/block} to functions.
	 */
	private function cbNamedBlocks($matches)
	{
		list(, $name, $content) = $matches;
		$func = '_cbb' . substr(md5(uniqid($name)), 0, 10) . '_' . preg_replace('#[^a-z0-9_]#i', '_', $name);
		$this->namedBlocks[$name] = "//\n// block $name\n//\n"
			. "if (!function_exists(\$_cb->blocks[" . var_export($name, TRUE) . "][] = '$func')) { function $func() { extract(func_get_arg(0))\n?>$content<?php\n}}";
		return '';
	}



	/**
	 * {foreach ...}
	 */
	private function macroForeach($content)
	{
		return '$iterator = $_cb->its[] = new SmartCachingIterator(' . preg_replace('# +as +#i', ') as ', $content, 1);
	}



	/**
	 * {attr ...}
	 */
	private function macroAttr($content)
	{
		return str_replace(') ', ')->', $content . ' ');
	}



	/**
	 * {contentType ...}
	 */
	private function macroContentType($content)
	{
		if (strpos($content, 'html') !== FALSE) {
			$this->filter->escape = 'TemplateHelpers::escapeHtml';
			$this->filter->context = CurlyBracketsFilter::CONTEXT_TEXT;

		} elseif (strpos($content, 'xml') !== FALSE) {
			$this->filter->escape = 'TemplateHelpers::escapeXml';
			$this->filter->context = CurlyBracketsFilter::CONTEXT_NONE;

		} elseif (strpos($content, 'javascript') !== FALSE) {
			$this->filter->escape = 'TemplateHelpers::escapeJs';
			$this->filter->context = CurlyBracketsFilter::CONTEXT_NONE;

		} elseif (strpos($content, 'css') !== FALSE) {
			$this->filter->escape = 'TemplateHelpers::escapeCss';
			$this->filter->context = CurlyBracketsFilter::CONTEXT_NONE;

		} elseif (strpos($content, 'plain') !== FALSE) {
			$this->filter->escape = '';
			$this->filter->context = CurlyBracketsFilter::CONTEXT_NONE;

		} else {
			$this->filter->escape = '$template->escape';
			$this->filter->context = CurlyBracketsFilter::CONTEXT_NONE;
		}

		// temporary solution
		return strpos($content, '/') ? /*\Nette\*/'Environment::getHttpResponse()->setHeader("Content-Type", "' . $content . '")' : '';
	}



	/**
	 * {dump ...}
	 */
	private function macroDump($content)
	{
		return $content ? "array('$content' => $content)" : 'get_defined_vars()';
	}



	/**
	 * {snippet ...}
	 */
	private function macroSnippet($content)
	{
		$args = array('');
		if ($snippet = CurlyBracketsFilter::fetchToken($content)) {  // [name [,]] [tag]
			$args[] = CurlyBracketsFilter::formatString($snippet);
		}
		if ($content) {
			$args[] = CurlyBracketsFilter::formatString($content);
		}
		return implode(', ', $args);
	}



	/**
	 * {widget ...}
	 */
	private function macroWidget($content, $modifiers)
	{
		// TODO: add support for $modifiers
		// TODO: check arguments
		$pair = explode(':', CurlyBracketsFilter::fetchToken($content), 2);
		$method = isset($pair[1]) ? ucfirst($pair[1]) : '';
		$method = preg_match('#^'.CurlyBracketsFilter::RE_IDENTIFIER.'|$#', $method) ? "render$method" : "{\"render$method\"}";
		return "\$control->getWidget(\"$pair[0]\")->$method({$this->filter->formatArray($content)})";
	}



	/**
	 * {link ...}
	 */
	private function macroLink($content, $modifiers)
	{
		return CurlyBracketsFilter::formatModifiers('$control->link(' . $this->formatLink($content) .')', $modifiers);
	}



	/**
	 * {plink ...}
	 */
	private function macroPlink($content, $modifiers)
	{
		return CurlyBracketsFilter::formatModifiers('$presenter->link(' . $this->formatLink($content) .')', $modifiers);
	}



	/**
	 * {ifCurrent ...}
	 */
	private function macroIfCurrent($content, $modifiers)
	{
		return $content ? CurlyBracketsFilter::formatModifiers('$presenter->link(' . $this->formatLink($content) .')', $modifiers) : '';
	}



	/**
	 * Formats {*link ...} parameters.
	 */
	private function formatLink($content)
	{
		return CurlyBracketsFilter::formatString(CurlyBracketsFilter::fetchToken($content)) . CurlyBracketsFilter::formatArray($content, ', '); // destination [,] args
	}



	/**
	 * {assign ...}
	 */
	private function macroAssign($content, $modifiers)
	{
		$param = ltrim(CurlyBracketsFilter::fetchToken($content), '$'); // [$]params value
		return '$' . $param . ' = ' . CurlyBracketsFilter::formatModifiers($content === '' ? 'NULL' : $content, $modifiers);
	}



	/**
	 * Escaping helper.
	 */
	private function macroEscape($content)
	{
		return $this->filter->escape;
	}



	/**
	 * Just modifiers helper.
	 */
	private function macroModifiers($content, $modifiers)
	{
		return CurlyBracketsFilter::formatModifiers($content, $modifiers);
	}



	/********************* run-time helpers ****************d*g**/



	/**
	 * Calls block.
	 * @param  array
	 * @param  string
	 * @param  array
	 * @return void
	 */
	public static function callBlock(& $blocks, $name, $params)
	{
		if (empty($blocks[$name])) {
			throw new /*\*/InvalidStateException("Call to undefined block '$name'.");
		}
		$block = reset($blocks[$name]);
		$block($params);
	}



	/**
	 * Calls parent block.
	 * @param  array
	 * @param  string
	 * @param  array
	 * @return void
	 */
	public static function callBlockParent(& $blocks, $name, $params)
	{
		if (empty($blocks[$name]) || ($block = next($blocks[$name])) === FALSE) {
			throw new /*\*/InvalidStateException("Call to undefined parent block '$name'.");
		}
		$block($params);
	}



	/**
	 * Includes subtemplate.
	 * @param  mixed      included file name or template
	 * @param  array      parameters
	 * @param  ITemplate  current template
	 * @return Template
	 */
	public static function includeTemplate($destination, $params, $template)
	{
		if ($destination instanceof ITemplate) {
			$tpl = $destination;

		} elseif ($destination == NULL) { // intentionally ==
			throw new /*\*/InvalidArgumentException("Template file name was not specified.");

		} else {
			$tpl = clone $template;
			if ($template instanceof IFileTemplate) {
				if (substr($destination, 0, 1) !== '/' && substr($destination, 1, 1) !== ':') {
					$destination = dirname($template->getFile()) . '/' . $destination;
				}
				$tpl->setFile($destination);
			}
		}

		$tpl->setParams($params); // interface?
		return $tpl;
	}



	/**
	 * Initializes state holder $_cb in template.
	 * @param  ITemplate
	 * @param  bool
	 * @param  string
	 * @return stdClass
	 */
	public static function initRuntime($template, $extends, $realFile)
	{
		$cb = (object) NULL;

		// extends support
		if (isset($template->_cb)) {
			$cb->blocks = & $template->_cb->blocks;
			$cb->templates = & $template->_cb->templates;
		}
		$cb->templates[$realFile] = $template;
		$cb->extends = is_bool($extends) ? $extends : (empty($template->_extends) ? FALSE : $template->_extends);
		unset($template->_cb, $template->_extends);

		// cache support
		if (!empty($cb->caches)) {
			end($cb->caches)->addFile($template->getFile());
		}

		return $cb;
	}

}
