# apac-magento-v1.x

Bambora APAC Magento v1.x extension
Bambora makes it easy for you as an online merchant to accept payments on your Magento v1.x ecommerce store by following this simple guide. This guide assumes that you have a running Community Edition Magento v1.x site and that your store runs on version 1.7.x or newer. 

------------------------------

This guide will provide you with the information required to setup Bambora APAC Online on your Magento 1.x store. Three basic steps are required in order to get up and running:
1. Download the extension
2. Install the extension
3. Configure the extension

We strongly recommend testing the extension in Sandbox mode on a staging or development environment prior to installing it on your production website.
## Download the extension
The latest version is available at GitHub.
1. Go to the [download page](https://github.com/bambora/apac-magento-v1.x/releases/latest)
2. Under the `Downloads` heading of the latest release, download the file ending with  `.tgz`.
Once the file has finished downloading you are ready to install the extension.
<a name="installextension"></a>
## Install the extension
In order to install the extension:
1. Log in to your Magento Administration (e.g. http://www.yourstore.com/admin)
2. Click `System`
3. Click `Magento Connect`
4. Click `Magento Connect Manager`
5. Locate the `Direct package file upload`-area
6. Click `Choose File` and select the downloaded file
7. Click `Install`
8. When the installation has completed click `Return to Admin`

![magento step 3a-1](/assets/images/magento-step-3a-1.png)
<label>Installation of the module: Steps 2 to 4</label>

![magento step 3a-2](/assets/images/magento-step-3a-2.png)
<label>Installation of the module: Steps 5 to 7</label>

The extension has now been installed and the final step is to configure the extension.
## Configure the extension
In order to access the configuration settings:
1. Click `System`
2. Click `Configuration`
3. Locate `Sales` in the menu on the left
4. Click `Payment Methods`
5. Locate `Bambora APAC Online`
6. Edit configuration (see [Mandatory Configurations](#mandatoryconfigurations) for details)
7. Click `Save Config`

![magento step 4-2](/assets/images/magento-step-4-1.png)
<label>Configuration of the extension: Steps 1 and 2</label>

![magento step 4-2](/assets/images/magento-step-4-2.png)
<label>Configuration of the extension: Steps 3 to 7</label>

When you have completed the configuration of the extension, you will need to flush the Magento Cache to ensure that these changes take effect:
1. Click `System`
2. Click `Cache Management`
3. Click `Flush Magento Cache`

Note: If you cannot see `Bambora APAC Online` in step 5 above, try logging out and logging back in to the Magento Administration or flushing the Magento Cache as described above.
<a name="mandatoryconfigurations"></a> 
### Mandatory configurations
There are a number of settings that must be completed to successfully configure the Bambora APAC Online extension. Most of the settings have been set to default values upon installation, however, the following settings are mandatory to finalise your installation:
* Mode (Sandbox or Live)
* Live API Username
* Live API Password
* Sandbox API Username
* Sandbox API Password

Ensure that the extension `Mode` is set to `Live` on your live environment and `Sandbox` on your development or staging environment. After you have completed the above mandatory configuration settings, you are now ready to offer Bambora as a payment method in your checkout.
In future, please follow the steps to [update the extension](#updateextension).

------
<a name="updateextension"></a>
## Updating the extension
Bambora is actively maintaining this extension and will be releasing regular updates with new features and enhancements. If you would like to update the extension, please follow these steps:
1. Click `System`
2. Click `Magento Connect`
3. Click `Magento Connect Manager`
4. Locate `Bambora APAC Online`
5. Select `Uninstall` in the Actions dropdown
6. Click `Commit Changes`
The steps are illustrated in the following image:
![magento step 3b-2](/assets/images/magento-step-3b-2.png)
<label>Updating the extension: Steps 4 to 6</label>

When the uninstall is completed, please proceed with the [installation of the extension](#installextension).