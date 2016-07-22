var fs = require('fs');
var config = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));

//read the test data
var testData = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));

var webdriver = require('selenium-webdriver');
var browser = new webdriver.Builder().usingServer().withCapabilities({'browserName': 'chrome' }).build();
 
browser.get(config.wagerplayerUrl);
browser.findElement(webdriver.By.css('[name="entered_login"]')).sendKeys(config.username);
browser.findElement(webdriver.By.css('[name="entered_password"]')).sendKeys(config.password);
browser.findElement(webdriver.By.css('[name="login"]')).click();

//select the category
var topFrame = browser.findElement(webdriver.By.css('#frame_top'));
var currentWindow = browser.getWindowHandle();
var mainFrame = browser.findElement(webdriver.By.css('[name="frame_bottom"]'));
var eventId;

//navigate the category dropdowns in the top frame
browser.switchTo().frame(topFrame);
browser.executeScript("$('#cat>option:contains(\""+testData.category+"\")').attr('selected','selected').parent().focus();");
browser.executeScript("$('#cat>option:contains(\""+testData.category+"\")').parent().change();");
//wait until the AJAX call finish
browser.wait(function(){
    return browser.findElements(webdriver.By.css('#subcat>option')).then(function(items){
        return items.length > 1;
    });
},300);

browser.executeScript("$('#subcat>option:contains(\""+testData.subCategory+"\")').attr('selected','selected').parent().focus();");
browser.executeScript("$('#subcat>option:contains(\""+testData.subCategory+"\")').parent().change();");
// //wait until the AJAX call finish
// browser.wait(function(){
//     return browser.findElements(webdriver.By.css('#event_id>option')).then(function(items){
//         return items.length > 1;
//     });
// },300);

browser.findElement(webdriver.By.css('[name="button_114"]')).click() //click on F4 Events button
.then(function(){
    browser.sleep(50);
    //switch to the main frame
    browser.switchTo().window(currentWindow);
    browser.switchTo().frame(mainFrame);
    browser.findElement(webdriver.By.css('[alt="New Event"]')).click(); //click on New Events button

    //enter the event information
    var today = new Date();
    var dd = today.getDate();
    var mm = today.getMonth()+1; //January is 0!
    var yyyy = today.getFullYear();

    browser.findElement(webdriver.By.css('[name="event_name"]')).sendKeys(testData.newEvent.name+"-"+yyyy+"-"+mm+"-"+dd);
    for (var i=0;i<testData.newEvent.selections.length;i++) {
        var selection = testData.newEvent.selections[i];
        if (i<testData.newEvent.selections.length - 1) {
            selection.name = selection.name +"\n";
        }
        browser.findElement(webdriver.By.css('[name="add_selections"]')).sendKeys(selection.name);    
    }

    //select Racing Live for the Market
    browser.executeScript("$('[name=\"event_market_type\"]>option:contains(\""+testData.market+"\")').attr('selected','selected');");

    //now insert the event
    browser.findElement(webdriver.By.css('[alt=" Insert "]')).click();

    //now we should be in the market screen
    browser.executeScript("toggleTable('market_settings')");
    //enter race number
    browser.findElement(webdriver.By.css('[name="race_num"]')).sendKeys("1");
    //then insert/update
    browser.findElement(webdriver.By.css('#f4_manage_button')).click();

    //the market page refresh, so we need to toggle the market_settings again
    browser.executeScript("toggleTable('market_settings')")
})
.then(function(){
    //now we add the product(s) we want to add
    for (var i=0;i<testData.products.length;i++) {
        if (testData.products[i].addToMarket) {
            browser.executeScript("$('[name=\"add_product_id[]\"]>option:contains(\""+testData.products[i].name+"\")').attr('selected','selected');");
            browser.findElement(webdriver.By.css('[name="add_prod"]')).click();
        }
    }
})
.then(function(){
    //disable Luxbook DVP if needed 
    //e.g when we have a BOG product
    if (testData.disableLuxbookDVP) {
        browser.findElements(webdriver.By.css('.toggle_buttons_'+testData.LuxBookDVPId)).then(function(buttons){
            for (var i=0;i<buttons.length;i++){
                if (i < 2) { //we only click the first 2 buttons (which are 2 Enable buttons)
                    buttons[i].click();
                }
            }
        });
    }
})
.then(function(){
    //enable the products we added
    for (var i=0;i<testData.products.length;i++) {
        //this one is an IIFE callback (Immediately Invoked Function Expression)
        //it allows us to pass the variable i at the moment the promise is created, not when it is resolved
        
        browser.findElements(webdriver.By.css('.toggle_buttons_'+testData.products[i].id)).then((function(i){
            return function(buttons){
                for (var j=0;j<buttons.length;j++){
                    //check the current value of the button
                    //determine if we need to click it or not
                    //we only want to enable a settings, not disable
                    buttons[j].findElement(webdriver.By.xpath("following-sibling::input[@class='toggle_values_"+testData.products[i].id+"']")).then((function(j){
                        return function(input){
                            input.getAttribute("value").then(function(value){
                                if (value != "1") {
                                    buttons[j].click();
                                }
                            });
                        }
                    })(j));
                }
            }
        })(i));
    }
})
.then(function(){
    //update the market 
    browser.findElement(webdriver.By.css('#f4_update_button')).click();
    //get the event id before going to the next step
    //switch to the top frame
    browser.switchTo().window(currentWindow);
    browser.switchTo().frame(topFrame);
})
.then(function(){
    //Go to F5-Liability
    //We need to click on the button twice for some unknown reason
    // browser.findElement(webdriver.By.css('[name="button_116"]')).click() //click on F5 Liability button
    browser.findElement(webdriver.By.css('[name="button_116"]')).click() //click on F5 Liability button
    //switch back to the main frame
    browser.switchTo().window(currentWindow);
    browser.switchTo().frame(mainFrame);
})
.then(function(){
    //update the market prices
    for (var i=0;i<testData.products.length;i++) {
        //win price first
        browser.findElements(webdriver.By.css('[product_id="'+testData.products[i].id+'"][bet_type="1"]')).then((function(i){
            return function(inputs){
                for (var j=0;j<inputs.length;j++){
                    inputs[j].click();
                    var actions = browser.actions();
                    actions.sendKeys(testData.newEvent.selections[j].prices[i].winPrice.toString()+"\n").perform();
                }
            }
        })(i));
        
        //place price
        browser.findElements(webdriver.By.css('[product_id="'+testData.products[i].id+'"][bet_type="2"]')).then((function(i){
            return function(inputs){
                for (var j=0;j<inputs.length;j++){
                    inputs[j].click();
                    var actions = browser.actions();
                    actions.sendKeys(testData.newEvent.selections[j].prices[i].placePrice.toString()+"\n").perform();
                }
            }
        })(i));
    }
})
;


