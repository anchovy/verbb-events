<?php
namespace verbb\events\controllers;

use verbb\events\Events;
use verbb\events\elements\PurchasedTicket;

use Craft;
use craft\web\Controller;
use craft\web\View;

class TicketController extends Controller
{
    // Properties
    // =========================================================================

    protected $allowAnonymous = true;


    // Public Methods
    // =========================================================================

    public function actionCheckin(array $variables = array())
    {
        $settings = Events::$plugin->getSettings();

		$sku = Craft::$app->getRequest()->getParam('sku');

        if ($settings->checkinLogin && !Craft::$app->getUser()->checkPermission('events-checkInTickets')) {
            return $this->_handleResponse([
                'success' => false,
                'message' => Craft::t('events', 'You do not have permission to check in tickets.'),
            ]);
        }
		
		if (!$sku) {
			return $this->_handleResponse([
				'success' => false,
				'message' => Craft::t('events', 'Missing required ticket SKU.'),
			]);
		}

		$purchasedTicket = PurchasedTicket::find()
			->ticketSku($sku)
			->one();

		if (!$purchasedTicket) {
			return $this->_handleResponse([
				'success' => false,
				'message' => Craft::t('events', 'Could not find ticket SKU.'),
			]);
		}

		if ($purchasedTicket->checkedIn) {
			return $this->_handleResponse([
				'success' => false,
				'message' => Craft::t('events', 'Ticket already checked in.'),
			]);
		}

		Events::$plugin->getPurchasedTickets()->checkInPurchasedTicket($purchasedTicket);

		return $this->_handleResponse([
			'success' => true,
			'purchasedTicket' => $purchasedTicket,
		]);
    }

    // Private Methods
    // =========================================================================

    private function _handleResponse($variables)
    {
        $settings = Events::$plugin->getSettings();

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson($variables);
		}
		
		$user = Craft::$app->getUser();
		
        if (Craft::$app->getRequest()->getIsCpRequest() && $user->checkPermission('accessCp') && $user->checkPermission('accessCpWhenSystemIsOff') && $user->checkPermission('events-managePurchasedTickets')) {
			if ($variables['success']) {
				Craft::$app->getSession()->setNotice(Craft::t('events', 'Ticket checked in.'));
			} else {
				Craft::$app->getSession()->setError(Craft::t('events', $variables['message']));	
			}

			return $this->redirect('events/purchased-tickets');
        }

        $oldMode = Craft::$app->getView()->getTemplateMode();
        $templateMode = View::TEMPLATE_MODE_CP;
        $template = 'events/check-in';

        if ($settings->checkinTemplate) {
            $templateMode = View::TEMPLATE_MODE_SITE;
            $template = $settings->checkinTemplate;
        }

        Craft::$app->getView()->setTemplateMode($templateMode);
        $html = Craft::$app->getView()->renderTemplate($template, $variables);
        Craft::$app->getView()->setTemplateMode($oldMode);

        return $html;
    }
}