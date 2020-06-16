# Magento Module Iop_Widget

## Tested on Version

* Magento 2.3.4

## Main Functionalities
* For B2B/B2C project widget to show 'Frequently Ordered Products' and 'Recently Ordered Products' for logged customer. 
* Manage custom widget(s) with products content on Magento 2.
* Use API with GraphQL endpoint to get Widget Instance.

##### NOTE: Screencast (5.5Mb): https://www.screencast.com/t/xcT8FTufDqL  If screencast issue then try use other browser.

### Features

* Create widget with specify to show product name | product image | product price | product link 
and product buttons [add to shopping cart,add to compare,add to wishlist].
* Specify via widget products quantity on frontend
* Specify via widget ordered products period (default: last 7 days products search)
* Added API with GraphQL endpoint to get Widget Instance, including it's rendered HTML.
* Supports Magento 2.0.0 and up

## Installation 

#### With Composer
Use the following commands to install this module into Magento 2:

    composer require iop/magento2-widget
    bin/magento module:enable Iop_Widget
    bin/magento setup:upgrade
       
#### Manual (without composer)
These are the steps:
* Upload the files in the folder `app/code/Iop/Widget` of your site
* Run `php -f bin/magento module:enable Iop_Widget`
* Run `php -f bin/magento setup:upgrade`
* Flush the Magento cache
* Done

## How to use it 

## Possibly GraphQL Query (https://your_domain.test/graphql)

![GraphQL_Playground](https://raw.githubusercontent.com/iop/widget/master/docs/GraphQL_Playground.png)

**Simple Query :**
```graphql
{
    widgetById (id:2) {
        id
        title
        theme_id
        html
        parameters{
            name
            value
        }
   }
}
```
