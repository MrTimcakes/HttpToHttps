# HttpToHttps

>The easy way to interface non TLS enabled devices to Https APIs - [HttpToHttps](http://HttpToHttps.xyz)

Http To Https was a project created by [MrTimcakes](https://MrTimcakes.com/) for proxying simple API requests.
The first use was for microcontrollers that can't quite manage TLS Https encryption, like the original [Espruino](https://espruino.com/) or ESP8266, to reach the Google Youtube Data API.
The service acts as a relativly dumb proxy, this could be improved but it worked for what I needed so I left it as is. By all means, if you want to improve it submit a pull request.

The project is devloped in PHP using the [Httpful](http://phphttpclient.com/) libary (Shipped with V0.2.19), the proxy portion of the code is actually just the proxy.php and .htaccess the rest of the code is purely cosmetic. The design for the website can be seen on [Codepen.io](https://codepen.io/MrTimcakes/pen/mEQoEG) as at the time of reading the domain may have expired. (That demo is purely cosmetic, the service will not work)

## Quick Start

To use this project you must setup an Apache enviroment (with Mod Rewrite enabled) then just navigate to the host address

### Support

If you're having any problem, please [raise an issue](https://github.com/MrTimcakes/HttpToHttps/issues/new) on GitHub.

### License

Hider is free Open-Source software, and is released under the GPL-3.0 License, further information can be found under the terms specified in the [license](https://github.com/MrTimcakes/HttpToHttps/blob/master/LICENSE).