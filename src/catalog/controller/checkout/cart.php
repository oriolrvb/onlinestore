<?php
namespace Opencart\Catalog\Controller\Checkout;

class Cart extends \Opencart\System\Engine\Controller {
    
    // The index method handles the main cart page
    public function index(): void {
        // Load the language file for the cart
        $this->load->language('checkout/cart');

        // Set the title of the document
        $this->document->setTitle($this->language->get('heading_title'));

        // Initialize an empty array for breadcrumbs
        $data['breadcrumbs'] = [];

        // Add home breadcrumb
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
        ];

        // Add cart breadcrumb
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'))
        ];

        // Check if cart has products or vouchers
        if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
            // Check if there are any stock errors or general errors
            if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
                $data['error_warning'] = $this->language->get('error_stock');
            } elseif (isset($this->session->data['error'])) {
                $data['error_warning'] = $this->session->data['error'];

                unset($this->session->data['error']);
            } else {
                $data['error_warning'] = '';
            }

            // Check if customer price is enabled and user is not logged in
            if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
                $data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')), $this->url->link('account/register', 'language=' . $this->config->get('config_language')));
            } else {
                $data['attention'] = '';
            }

            // Check if success message is set
            if (isset($this->session->data['success'])) {
                $data['success'] = $this->session->data['success'];

                unset($this->session->data['success']);
            } else {
                $data['success'] = '';
            }

            // Check if cart weight display is enabled
            if ($this->config->get('config_cart_weight')) {
                $data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
            } else {
                $data['weight'] = '';
            }

            // Load the cart list controller and store the output in data['list']
            $data['list'] = $this->load->controller('checkout/cart.getList');

            $data['modules'] = [];

$this->load->model('setting/extension');

// Get extensions of type 'total'
$extensions = $this->model_setting_extension->getExtensionsByType('total');

foreach ($extensions as $extension) {
    // Load the corresponding controller for each extension
    $result = $this->load->controller('extension/' . $extension['extension'] . '/total/' . $extension['code']);

    // Check if the result is not an exception
    if (!$result instanceof \Exception) {
        $data['modules'][] = $result;
    }
}

$data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
$data['checkout'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));

$data['column_left'] = $this->load->controller('common/column_left');
$data['column_right'] = $this->load->controller('common/column_right');
$data['content_top'] = $this->load->controller('common/content_top');
$data['content_bottom'] = $this->load->controller('common/content_bottom');
$data['footer'] = $this->load->controller('common/footer');
$data['header'] = $this->load->controller('common/header');

// Set the output of the response to the cart view
$this->response->setOutput($this->load->view('checkout/cart', $data));

// If the cart is empty or has no products/vouchers
} else {
    $data['text_error'] = $this->language->get('text_no_results');

    $data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));

    $data['column_left'] = $this->load->controller('common/column_left');
    $data['column_right'] = $this->load->controller('common/column_right');
    $data['content_top'] = $this->load->controller('common/content_top');
    $data['content_bottom'] = $this->load->controller('common/content_bottom');
    $data['footer'] = $this->load->controller('common/footer');
    $data['header'] = $this->load->controller('common/header');

    // Set the output of the response to the 'not_found' error view
    $this->response->setOutput($this->load->view('error/not_found', $data));
}


public function list(): void {
    $this->load->language('checkout/cart');

    // Set the output of the response to the result of the getList() method
    $this->response->setOutput($this->getList());
}

public function getList(): string {
    $data['list'] = $this->url->link(' ', 'language=' . $this->config->get('config_language'));
    $data['product_edit'] = $this->url->link('checkout/cart.edit', 'language=' . $this->config->get('config_language'));
    $data['product_remove'] = $this->url->link('checkout/cart.remove', 'language=' . $this->config->get('config_language'));
    $data['voucher_remove'] = $this->url->link('checkout/voucher.remove', 'language=' . $this->config->get('config_language'));

    // Load required models
    $this->load->model('tool/image');
    $this->load->model('tool/upload');

    $data['products'] = [];

    // Load the cart model
    $this->load->model('checkout/cart');

    // Get the products in the cart
    $products = $this->model_checkout_cart->getProducts();

    foreach ($products as $product) {
        if (!$product['minimum']) {
            $data['error_warning'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
        }

        // Display prices
        if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
            $unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));

            $price = $this->currency->format($unit_price, $this->session->data['currency']);
            $total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
        } else {
            $price = false;
            $total = false;
        }

        $description = '';

        if ($product['subscription']) {
            // Handle subscription details
            if ($product['subscription']['trial_status']) {
                $trial_price = $this->currency->format($this->tax->calculate($product['subscription']['trial_price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                $trial_cycle = $product['subscription']['trial_cycle'];
                $trial_frequency = $this->language->get('text_' . $product['subscription']['trial_frequency']);
                $trial_duration = $product['subscription']['trial_duration'];

                $description .= sprintf($this->language->get('text_subscription_trial'), $trial_price, $trial_cycle, $trial_frequency, $trial_duration);
            }

            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                $price = $this->currency->format($this->tax->calculate($product['subscription']['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
            }

            $cycle = $product['subscription']['cycle'];
            $frequency = $this->language->get('text_' . $product['subscription']['frequency']);
            $duration = $product['subscription']['duration'];

            if ($duration) {
                $description .= sprintf($this->language->get('text_subscription_duration'), $price, $cycle, $frequency, $duration);
            } else {
                $description .= sprintf($this->language->get('text_subscription_cancel'), $price, $cycle, $frequency);
            }
        }

        // Build the product data
        $data['products'][] = [
            'cart_id'      => $product['cart_id'],
            'thumb'        => $product['image'],
            'name'         => $product['name'],
            'model'        => $product['model'],
            'option'       => $product['option'],
            'subscription' => $description,
            'quantity'     => $product['quantity'],
            'stock'        => $product['stock'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
            'minimum'      => $product['minimum'],
            'reward'       => $product['reward'],
            'price'        => $price,
            'total'        => $total,
            'href'         => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id'])
        ];
    }

    // Gift Vouchers
    $data['vouchers'] = [];

    $vouchers = $this->model_checkout_cart->getVouchers();

    foreach ($vouchers as $key => $voucher) {
        $data['vouchers'][] = [
            'key'         => $key,
            'description' => $voucher['description'],
            'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency'])
        ];
    }

    $data['totals'] = [];

    $totals = [];
    $taxes = $this->cart->getTaxes();
    $total = 0;

    // Display prices
    if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

        foreach ($totals as $result) {
            $data['totals'][] = [
                'title' => $result['title'],
                'text'  => $this->currency->format($result['value'], $this->session->data['currency'])
            ];
        }
    }

    // Return the rendered view
    return $this->load->view('checkout/cart_list', $data);
}

/*
This method is responsible for adding a product to the shopping cart. Here's a breakdown with comments explaining each section:

    The method starts by loading the language file for cart-related translations and initializing an empty JSON array to store the response data.

    The product ID and quantity are obtained from the request data. If they are not provided, default values of 0 and 1 are used, respectively.

    The selected options are retrieved from the request data. If no options are selected, an empty array is created.

    The selected subscription plan ID is obtained from the request data. If it is not provided, a default value of 0 is used.

    The product model is loaded to access product-related functionalities.

    The product information is retrieved using the product ID.

    If the product information exists, it checks if the product is a variant. If so, it gets the master product ID.

    It retrieves the variant override options from the product information. If they exist, they are assigned to the $override variable; otherwise, an empty array is used.

    The variant options are merged with the selected options. If an option exists in the $override array, it takes precedence.

    It validates the selected options by retrieving the product options and checking if any required options are missing. If a required option is missing, an error message is added to the JSON response.

    It validates subscription products by checking if the selected subscription plan ID is valid for the product. If it is not valid, an error message is added to the JSON response.

    If the product information does not exist, an error message is added to the JSON response.

    If there are no errors in the JSON response, the product is added to the cart using the add() method of the cart object. The success message is set, and the shipping/payment methods are unset.

    If there are errors, a redirect URL to the product page is set in the JSON response.

    The response is then encoded as JSON and sent back with the appropriate content type header.


*/
    
public function add(): void {
    $this->load->language('checkout/cart');

    $json = [];

    // Get the product ID from the request data
    if (isset($this->request->post['product_id'])) {
        $product_id = (int)$this->request->post['product_id'];
    } else {
        $product_id = 0;
    }

    // Get the quantity from the request data
    if (isset($this->request->post['quantity'])) {
        $quantity = (int)$this->request->post['quantity'];
    } else {
        $quantity = 1;
    }

    // Get the selected options from the request data
    if (isset($this->request->post['option'])) {
        $option = array_filter($this->request->post['option']);
    } else {
        $option = [];
    }

    // Get the selected subscription plan ID from the request data
    if (isset($this->request->post['subscription_plan_id'])) {
        $subscription_plan_id = (int)$this->request->post['subscription_plan_id'];
    } else {
        $subscription_plan_id = 0;
    }

    // Load the product model
    $this->load->model('catalog/product');

    // Get the product information
    $product_info = $this->model_catalog_product->getProduct($product_id);

    if ($product_info) {
        // If the product is a variant, get the master product ID
        if ($product_info['master_id']) {
            $product_id = $product_info['master_id'];
        }

        // Get the variant override options
        if (isset($product_info['override']['variant'])) {
            $override = $product_info['override']['variant'];
        } else {
            $override = [];
        }

        // Merge variant options with selected options
        foreach ($product_info['variant'] as $key => $value) {
            if (array_key_exists($key, $override)) {
                $option[$key] = $value;
            }
        }

        // Validate selected options
        $product_options = $this->model_catalog_product->getOptions($product_id);

        foreach ($product_options as $product_option) {
            if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
                $json['error']['option_' . $product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
            }
        }

        // Validate subscription products
        $subscriptions = $this->model_catalog_product->getSubscriptions($product_id);

        if ($subscriptions) {
            $subscription_plan_ids = [];

            foreach ($subscriptions as $subscription) {
                $subscription_plan_ids[] = $subscription['subscription_plan_id'];
            }

            if (!in_array($subscription_plan_id, $subscription_plan_ids)) {
                $json['error']['subscription'] = $this->language->get('error_subscription');
            }
        }
    } else {
        $json['error']['warning'] = $this->language->get('error_product');
    }

    if (!$json) {
        // Add the product to the cart
        $this->cart->add($product_id, $quantity, $option, $subscription_plan_id);

        // Set success message and unset shipping/payment methods
        $json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id), $product_info['name'], $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language')));
        unset($this->session->data['shipping_method']);
        unset($this->session->data['shipping_methods']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
    } else {
        // Set redirect URL if there are errors
        $json['redirect'] = $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id, true);
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
}

/*
This method is responsible for editing the quantity of an item in the shopping cart. It first loads the language file for cart-related translations. Then, it initializes an empty JSON array to store the response data.

The method retrieves the cart item key and quantity from the request data. If the key or quantity is not provided, default values of 0 and 1 are used, respectively.

It checks if the specified cart item exists in the cart. If the item does not exist, an error message is added to the JSON response.

If no error is encountered, the method updates the quantity of the cart item using the update() method of the cart object.

After updating the quantity, it checks if the cart still has products or if there are any vouchers in the session data. If there are, a success message is added to the JSON response. Otherwise, if the cart is empty, a redirect URL to the cart page is provided.

Lastly, the method unsets the shipping and payment methods, as well as the reward points from the session data. The response is then encoded as JSON and sent back with the appropriate content type header.
*/

public function edit(): void {
    $this->load->language('checkout/cart');

    $json = [];

    // Get the cart item key from the request data
    if (isset($this->request->post['key'])) {
        $key = (int)$this->request->post['key'];
    } else {
        $key = 0;
    }

    // Get the quantity from the request data
    if (isset($this->request->post['quantity'])) {
        $quantity = (int)$this->request->post['quantity'];
    } else {
        $quantity = 1;
    }

    // Check if the cart item exists
    if (!$this->cart->has($key)) {
        $json['error'] = $this->language->get('error_product');
    }

    if (!$json) {
        // Update the quantity of the cart item
        $this->cart->update($key, $quantity);

        if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
            // Set success message if the cart still has products or vouchers
            $json['success'] = $this->language->get('text_edit');
        } else {
            // Set redirect URL to cart page if the cart is empty
            $json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
        }

        // Unset shipping/payment methods and reward points
        unset($this->session->data['shipping_method']);
        unset($this->session->data['shipping_methods']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['reward']);
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
}

/*
This method is responsible for removing an item from the shopping cart. It checks if the specified cart item key exists, and if so, removes it from the cart. If the cart still has products or vouchers after the removal, a success message is set. Otherwise, if the cart is empty, a redirect URL to the cart page is provided. Shipping and payment methods, as well as reward points, are unset from the session data. Finally, the response is encoded as JSON and sent back with the appropriate content type header.
*/

public function remove(): void {
    $this->load->language('checkout/cart');

    $json = [];

    // Get the cart item key from the request data
    if (isset($this->request->post['key'])) {
        $key = (int)$this->request->post['key'];
    } else {
        $key = 0;
    }

    // Check if the cart item exists
    if (!$this->cart->has($key)) {
        $json['error'] = $this->language->get('error_product');
    }

    // Remove the cart item
    if (!$json) {
        $this->cart->remove($key);

        if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
            // Set success message if the cart still has products or vouchers
            $json['success'] = $this->language->get('text_remove');
        } else {
            // Set redirect URL to cart page if the cart is empty
            $json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
        }

        // Unset shipping/payment methods and reward points
        unset($this->session->data['shipping_method']);
        unset($this->session->data['shipping_methods']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['reward']);
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
}

