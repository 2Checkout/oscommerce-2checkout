### _[Signup free with 2Checkout and start selling!](https://www.2checkout.com/referral?r=git2co)_

How to integrate osCommerce with 2Checkout
-------------------------------------------

### osCommerce Settings:

1. Download or clone https://github.com/2Checkout/oscommerce-2checkout.git
2. Upload the files in the **catalog** directory to your osCommerce directory
3. Sign in to your osCommerce admin
4. Click **Modules**
5. Click **Payment**
6. Click **Install** on **2Checkout**
7. Enter your **2Checkout Account ID** _(Merchant Code, Found in your 2Checkout Control Panel)_
8. Enter your **Secret Key** _(Found in your 2Checkout Control Panel)_
9. Enter your **Secret Word** _(Found in your 2Checkout Control Panel)_
10. Under **Test Mode** select **No** for live sales or **Yes** for test sales.
11. Click **Save Changes**


### 2Checkout Settings

1. Sign in to your 2Checkout account. 
2. Navigate to Dashboard → Integrations → Webhooks & API Section
3. Make sure to enable the IPN webhook notification in your Merchant Control Panel.
	- Log in to the 2Checkout Merchant Control Panel and navigate to Integrations → Webhooks & API
	- Scroll down to the Notifications section and enable the IPN webhook
	- For the Payment notification type field, select IPN or Email Text & IPN, and then click on the Configure IPN button.
	- On the IPN settings page, click on the Add IPN URL button and input the IPN URL available in the configuration page in osCommerce.
	- Enable all triggers and response tags

Please feel free to contact 2Checkout directly with any integration questions via supportplus@2checkout.com.
