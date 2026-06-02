# iwocaPay plugin for Magento2
This [iwocaPay](https://www.iwoca.co.uk/iwocapay-sellers/) plugin for Magento 2 will implement the iwocaPay solution in your store.

## Installation
You can install this plugin using Composer:
```shell
composer require iwoca/iwocapay-magento
```

## Enable the module
```shell
bin/magento module:enable Iwoca_Iwocapay
bin/magento setup:upgrade
```

## Configuration
You can find all related configurations for this module by navigating to "Stores > Configuration > Sales > Payment Methods > iwocaPay" in the Magento admin section.

### Configuration Options
| Configuration                     | Description                                                                                                                                  |
|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| Enabled                           | Turns the iwocaPay payment solution on or off                                                                                                |
| Seller Access Token               | The access token which is used to authenticate requests made to the iwocaPay API                                                             |
| Seller ID                         | Your Seller ID which is used to identify you as a seller in the iwocaPay system                                                              |
| Mode                              | Indicates if the module is running in testing (Staging) or production mode                                                                   |
| Payment from Applicable Countries | Indicates if the module can be used for all allowed countries, or a specific set of countries                                                |
| Payment from Specific Countries   | If the "Payment from Applicable Countries" is set to "Specific countries" this is used to select which countries are allowed to use iwocaPay |
| Debug mode                        | Add additional logging during the payment process.                                                                                           |

## License
MIT license. For more information, see the [LICENSE](LICENSE.txt) file.
