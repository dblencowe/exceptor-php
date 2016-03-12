# exceptor-php

Setup with the following:
```
require "exceptor-php/lib/Exceptor/Autoloader.php";
Exceptor_Autoloader::register();
$excpetor = new Exceptor_HandleError();
```

Either declare your DSN as the environment variable "EXCEPTOR_DSN" or pass it in to the Exceptor_ErrorHandler class as a string
