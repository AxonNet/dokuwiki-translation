<?php
namespace org\dokuwiki\translatorBundle\Services\Language;

class LanguageFileParserTestDummy extends LanguageFileParser {


    function __construct() {
        $this->author = new AuthorList();
    }

    public function setContent($content) {
        $this->content = $content;
    }

    public function getContent() {
        return $this->content;
    }

    public function setAuthor($author) {
        $this->author = $author;
    }

    public function getAuthor() {
        return $this->author;
    }

    public function getLang($key) {
        return $this->lang[$key];
    }

    public function getAllLang() {
        return $this->lang;
    }
}

class LanguageFileParserTest extends \PHPUnit_Framework_TestCase {

    function testGoToStart() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('<?php');
        $parser->goToStart();
        $this->assertEquals('', $parser->getContent());

        $parser->setContent('some Text<?php');
        $parser->goToStart();
        $this->assertEquals('', $parser->getContent());

        $parser->setContent('<?phpa');
        $parser->goToStart();
        $this->assertEquals('a', $parser->getContent());

        $parser->setContent('<?php<?php');
        $parser->goToStart();
        $this->assertEquals('<?php', $parser->getContent());
    }

    function testDetermineNextMode() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('/* Bla bla */');
        $this->assertEquals(LanguageFileParser::$MODE_COMMENT_MULTI_LINE, $parser->determineNextMode());
        $this->assertEquals(' Bla bla */', $parser->getContent());

        $parser->setContent('// Bla');
        $this->assertEquals(LanguageFileParser::$MODE_COMMENT_SINGLE_LINE, $parser->determineNextMode());
        $this->assertEquals(' Bla', $parser->getContent());

        $parser->setContent('$lang["some"] = "text";');
        $this->assertEquals(LanguageFileParser::$MODE_LANG, $parser->determineNextMode());
        $this->assertEquals('"some"] = "text";', $parser->getContent());

        $parser->setContent('$lang[\'js\']["some"] = "text";');
        $this->assertEquals(LanguageFileParser::$MODE_LANG, $parser->determineNextMode());
        $this->assertEquals('\'js\']["some"] = "text";', $parser->getContent());

        $parser->setContent('echo "bla";');
        $this->assertEquals(LanguageFileParser::$MODE_PHP_UNKNOWN, $parser->determineNextMode());
        $this->assertEquals('echo "bla";', $parser->getContent());
    }

    function testIssue52() {
        $parser = new LanguageFileParserTestDummy();
        $parser->setContent('# Bla');
        $this->assertEquals(LanguageFileParser::$MODE_COMMENT_SINGLE_LINE, $parser->determineNextMode());
        $this->assertEquals(' Bla', $parser->getContent());

    }

    function testProcessMultiLineComment() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent("some text\n * @var string some text\n   * @author some one üß <email.address@someone>\n*/");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());
        $expected = new AuthorList();
        $expected->add(new Author('some one üß', 'email.address@someone'));
        $this->assertEquals($expected, $parser->getAuthor());
        $this->assertEquals('', $parser->getContent());

        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n   * @author some one üß <email.address@someone>\n* @author an other <email.some@bla>\n*/ text");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());
        $expected = new AuthorList();
        $expected->add(new Author('some one üß', 'email.address@someone'));
        $expected->add(new Author('an other', 'email.some@bla'));
        $this->assertEquals($expected, $parser->getAuthor());
        $this->assertEquals(' text', $parser->getContent());

        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n   * @author one <one@example.com>\n* @author one <one@example.com>\n*/ text");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());
        $expected = new AuthorList();
        $expected->add(new Author('one', 'one@example.com'));
        $this->assertEquals($expected, $parser->getAuthor());
        $this->assertEquals(' text', $parser->getContent());

        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n   * @author two <one@example.com>\n* @author one <one@example.com>\n*/ text");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());
        $expected = new AuthorList();
        $expected->add(new Author('two', 'one@example.com'));
        $expected->add(new Author('one', 'one@example.com'));
        $this->assertEquals($expected, $parser->getAuthor());
        $this->assertEquals(' text', $parser->getContent());

        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n   * @author one <one@example.com>\n* @author two <one@example.com>\n*/ text");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());
        $expected = new AuthorList();
        $expected->add(new Author('one', 'one@example.com'));
        $expected->add(new Author('two', 'one@example.com'));
        $this->assertEquals($expected, $parser->getAuthor());
        $this->assertEquals(' text', $parser->getContent());


        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n\n*/");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());
        $this->assertEquals(new AuthorList(), $parser->getAuthor());
        $this->assertEquals('', $parser->getContent());
    }

    function testIssue48() {
        $parser = new LanguageFileParserTestDummy();
        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n   * @author one <one@example.com>\n* @author      one <one@example.com>\n*/ text");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());

        $expected = new AuthorList();
        $expected->add(new Author('one', 'one@example.com'));
        $this->assertEquals($expected, $parser->getAuthor());
        $this->assertEquals(' text', $parser->getContent());

    }

    function testIssue38() {
        $parser = new LanguageFileParserTestDummy();
        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n   * @author: one <one@example.com>\n*/ text");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processMultiLineComment());

        $expected = new AuthorList();
        $expected->add(new Author('one', 'one@example.com'));
        $this->assertEquals($expected, $parser->getAuthor());
        $this->assertEquals(' text', $parser->getContent());

    }

    /**
     * @expectedException \org\dokuwiki\translatorBundle\Services\Language\LanguageParseException
     */
    function testProcessMultiLineCommentParserException() {
        $parser = new LanguageFileParserTestDummy();
        $parser->setAuthor(new AuthorList());
        $parser->setContent("some text\n * @var string some text\n\n");
        $parser->processMultiLineComment();
    }

    function testProcessSingleLineComment() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent(" hello you");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processSingleLineComment());
        $this->assertEquals('', $parser->getContent());

        $parser->setContent(" hello you\nmore php code");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processSingleLineComment());
        $this->assertEquals('more php code', $parser->getContent());
    }

    function testGetFirstString() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('"Hello"');
        $this->assertEquals('Hello', $parser->getFirstString());
        $this->assertEquals('', $parser->getContent());

        $parser->setContent('"Hello" some other meaningful stuff');
        $this->assertEquals('Hello', $parser->getFirstString());
        $this->assertEquals(' some other meaningful stuff', $parser->getContent());

        $parser->setContent("'Hello'");
        $this->assertEquals('Hello', $parser->getFirstString());
        $this->assertEquals('', $parser->getContent());

        $parser->setContent('"\""');
        $this->assertEquals('"', $parser->getFirstString());
        $this->assertEquals('', $parser->getContent());

        $parser->setContent("'\\''");
        $this->assertEquals("'", $parser->getFirstString());
        $this->assertEquals('', $parser->getContent());

        $parser->setContent('""');
        $this->assertEquals('', $parser->getFirstString());
        $this->assertEquals('', $parser->getContent());
    }

    function testGetString() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('"Hello" . \' whats up\'');
        $this->assertEquals('Hello whats up', $parser->getString());
        $this->assertEquals('', $parser->getContent());
    }

    /**
     * @expectedException \org\dokuwiki\translatorBundle\Services\Language\LanguageParseException
     */
    function testGetStringUnknownEnd() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('"Hello . \' whats up\'');
        $parser->getString();
    }

    function testProcessLang() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('"Key"] = "value";');
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processLang());
        $this->assertEquals('', $parser->getContent());
        $this->assertEquals('value', $parser->getLang('Key'));

        $parser->setContent("'another']\t =  \n 'value' ;");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processLang());
        $this->assertEquals('', $parser->getContent());
        $this->assertEquals('value', $parser->getLang('another'));

        $parser->setContent('"Key"]="value";');
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processLang());
        $this->assertEquals('', $parser->getContent());
        $this->assertEquals('value', $parser->getLang('Key'));
    }

    function testProcessJsLang() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('\'js\']["Key"] = "value";');
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processLang());
        $this->assertEquals('', $parser->getContent());
        $this->assertEquals(array('Key' => 'value'), $parser->getLang('js'));

        $parser->setContent("'js'  ]\t [\n  \"Key\"] = \"value\";");
        $this->assertEquals(LanguageFileParser::$MODE_PHP, $parser->processLang());
        $this->assertEquals('', $parser->getContent());
        $this->assertEquals(array('Key' => 'value'), $parser->getLang('js'));
    }

    /**
     * @expectedException \org\dokuwiki\translatorBundle\Services\Language\LanguageParseException
     */
    function testProcessLangException() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('"Key"] = "value"');
        $parser->processLang();
    }

    /**
     * @expectedException \org\dokuwiki\translatorBundle\Services\Language\LanguageParseException
     */
    function testProcessLangExceptionSyntax() {
        $parser = new LanguageFileParserTestDummy();

        $parser->setContent('"Key" = "value"');
        $parser->processLang();
    }

    function testCompleteFile() {
        $parser = new LanguageFileParserTestDummy();
        $parser->loadFile(dirname(__FILE__) . '/testLang.php');
        $parser->parse();

        $this->assertEquals(18, count($parser->getAuthor()->getAll()));
        $this->assertEquals(268, count($parser->getAllLang()));
        $this->assertEquals(41, count($parser->getLang('js')));
    }

    function testCompleteFileWithClosing() {
        $parser = new LanguageFileParserTestDummy();
        $content = trim(file_get_contents(dirname(__FILE__) . '/testLang.php'));
        $content .= "\n\n?>";
        $parser->setContent($content);
        $parser->parse();

        $this->assertEquals(18, count($parser->getAuthor()->getAll()));
        $this->assertEquals(268, count($parser->getAllLang()));
        $this->assertEquals(41, count($parser->getLang('js')));
    }


    function testEscapeSingleQuoted() {
        $parser = new LanguageFileParserTestDummy();

        $this->assertEquals('\'', $parser->escapeString("\\'", '\''));
        $this->assertEquals('\\', $parser->escapeString("\\\\", '\''));
        $this->assertEquals('\\n', $parser->escapeString("\\n", '\''));
    }

    function testEscapeDoubleQuoted() {
        $parser = new LanguageFileParserTestDummy();

        $this->assertEquals("\n", $parser->escapeString('\\n', '"'));
        $this->assertEquals("\r", $parser->escapeString('\\r', '"'));
        $this->assertEquals("\t", $parser->escapeString('\\t', '"'));
        $this->assertEquals("\v", $parser->escapeString('\\v', '"'));
        $this->assertEquals("\e", $parser->escapeString('\\e', '"'));
        $this->assertEquals("\f", $parser->escapeString('\\f', '"'));
        $this->assertEquals("\\", $parser->escapeString('\\\\', '"'));
        $this->assertEquals("$", $parser->escapeString('\\$', '"'));
        $this->assertEquals('"', $parser->escapeString('\\"', '"'));

        $this->assertEquals('A', $parser->escapeString('\\x41', '"'));
        $this->assertEquals('A', $parser->escapeString('\\101', '"'));
    }
}
