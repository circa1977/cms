<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftunit\web;

use Codeception\Test\Unit;
use Craft;
use craft\helpers\UrlHelper;
use craft\test\mockclasses\controllers\TestController;
use craft\web\Response;
use craft\web\View;
use UnitTester;
use yii\base\Action;
use yii\base\Exception;
use yii\base\ExitException;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\web\BadRequestHttpException;

/**
 * Unit tests for Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.1
 */
class ControllerTest extends Unit
{
    // Public Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestController
     */
    private $controller;

    // Public Methods
    // =========================================================================

    // Tests
    // =========================================================================

    /**
     *
     */
    public function testBeforeAction()
    {
        $this->tester->expectThrowable(ExitException::class, function() {
            // AllowAnonymous should redirect and Craft::$app->exit(); I.E. An exit exception
            $this->controller->beforeAction(new Action('not-allow-anonymous', $this->controller));
        });

        $this->assertTrue($this->controller->beforeAction(new Action('allow-anonymous', $this->controller)));
    }

    /**
     * @throws InvalidRouteException
     */
    public function testRunActionJsonError()
    {
        // We accept JSON.
        Craft::$app->getRequest()->setAcceptableContentTypes(['application/json' => true]);
        Craft::$app->getRequest()->headers->set('Accept', 'application/json');

        /* @var Response $resp */
        $resp = $this->controller->runAction('me-dont-exist');

        // As long as this is set. We can expect yii to do its thing.
        $this->assertSame(Response::FORMAT_JSON, $resp->format);
    }

    /**
     * @throws Exception
     */
    public function testTemplateRendering()
    {
        // We need to render a template from the site dir.
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $response = $this->controller->renderTemplate('template');

        // Again. If this is all good. We can expect Yii to do its thing.
        $this->assertSame('Im a template!', $response->data);
        $this->assertSame(Response::FORMAT_RAW, $response->format);
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaders()->get('content-type'));
    }

    /**
     * If the content-type headers are already set. Render Template should ignore attempting to set them.
     *
     * @throws Exception
     */
    public function testTemplateRenderingIfHeadersAlreadySet()
    {
        // We need to render a template from the site dir.
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
        Craft::$app->getResponse()->getHeaders()->set('content-type', 'HEADERS');

        $response = $this->controller->renderTemplate('template');

        // Again. If this is all good. We can expect Yii to do its thing.
        $this->assertSame('Im a template!', $response->data);
        $this->assertSame(Response::FORMAT_RAW, $response->format);
        $this->assertSame('HEADERS', $response->getHeaders()->get('content-type'));
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function testRedirectToPostedUrl()
    {
        $baseUrl = $this->_getBaseUrlForRedirect();
        $redirect = Craft::$app->getSecurity()->hashData('craft/do/stuff');

        // Default
        $default = $this->controller->redirectToPostedUrl();

        // Test that with nothing passed in. It defaults to the base. See self::getBaseUrlForRedirect() for more info.
        $this->assertSame(
            $baseUrl,
            $default->headers->get('Location')
        );

        // What happens when we pass in a param.
        Craft::$app->getRequest()->setBodyParams(['redirect' => $redirect]);
        $default = $this->controller->redirectToPostedUrl();
        $this->assertSame($baseUrl . '?p=craft/do/stuff', $default->headers->get('Location'));
    }

    /**
     * @throws BadRequestHttpException
     */
    public function testRedirectToPostedWithSetDefault()
    {
        $baseUrl = $this->_getBaseUrlForRedirect();
        $withDefault = $this->controller->redirectToPostedUrl(null, 'craft/do/stuff');
        $this->assertSame($baseUrl . '?p=craft/do/stuff', $withDefault->headers->get('Location'));
    }

    /**
     *
     */
    public function testAsJsonP()
    {
        $result = $this->controller->asJsonP(['test' => 'test']);
        $this->assertSame(Response::FORMAT_JSONP, $result->format);
        $this->assertSame(['test' => 'test'], $result->data);
    }

    /**
     *
     */
    public function testAsRaw()
    {
        $result = $this->controller->asRaw(['test' => 'test']);
        $this->assertSame(Response::FORMAT_RAW, $result->format);
        $this->assertSame(['test' => 'test'], $result->data);
    }

    /**
     *
     */
    public function testAsErrorJson()
    {
        $result = $this->controller->asErrorJson('im an error');
        $this->assertSame(Response::FORMAT_JSON, $result->format);
        $this->assertSame(['error' => 'im an error'], $result->data);
    }

    /**
     *
     */
    public function testRedirect()
    {
        $this->assertSame(
            $this->_getBaseUrlForRedirect() . '?p=do/stuff',
            $this->controller->redirect('do/stuff')->headers->get('Location')
        );

        // Based on the entryScript param in the craft module. If nothing is passed in the Craft::$app->getHomeUrl(); will be called
        // Which will redirect to the $_SERVER['SCRIPT_NAME'] param.
        $this->assertSame(
            'index.php',
            $this->controller->redirect(null)->headers->get('Location')
        );

        // Absolute url
        $this->assertSame(
            'https://craftcms.com',
            $this->controller->redirect('https://craftcms.com')->headers->get('Location')
        );

        // Custom status code
        $this->assertSame(
            500,
            $this->controller->redirect('https://craftcms.com', 500)->statusCode
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function _before()
    {
        parent::_before();
        $_SERVER['REQUEST_URI'] = 'https://craftcms.com/admin/dashboard';
        $this->controller = new TestController('test', Craft::$app);
    }

    // Private Methods
    // =========================================================================

    private function _determineUrlScheme(): string
    {
        return !Craft::$app->getRequest()->getIsConsoleRequest() && Craft::$app->getRequest()->getIsSecureConnection() ? 'https' : 'http';
    }

    private function _getBaseUrlForRedirect(): string
    {
        $scheme = $this->_determineUrlScheme();
        return UrlHelper::urlWithScheme(Craft::$app->getConfig()->getGeneral()->siteUrl . 'index.php', $scheme);
    }
}
