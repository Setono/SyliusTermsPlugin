# Browser tests

How to write browser tests (`tests/Browser/`) that pass every run. They use playwright-symfony: Chromium sends each request to the test's own PHP process, which handles it in the test's kernel, inside the test's DAMA transaction.

## Waiting

The PHP process serves the page's requests only while it is inside a Playwright call. Wait through Playwright: `waitForLoadState()`, `waitForResponse()`, `waitForFunction()` and `expect()` from `Playwright\Testing`. A `usleep()` stalls the page, because nothing answers its requests while PHP sleeps.

- After `visit()`, call `$page->waitForLoadState('networkidle')` before the first fill or click, so Bootstrap and the Live components have bound their handlers.
- A changed field re-renders the Live form. Before a submit, wait for that re-render, or the submit's navigation can abort. Run the change inside the wait:

    ```php
    $page->waitForResponse('**/_components/**', ['action' => 'document.querySelector(\'[data-test-label="de_DE"]\').blur()']);
    $page->waitForFunction('() => null === document.querySelector("[data-controller~=live][busy]")');
    ```

- A re-render keeps what was entered in a field only if the Live form saw the field's `input` or `change` event. A value set without them, such as by `$locator->evaluate('input => input.value = "Cookies"')` or by a `fill()` before the Live form had bound its handlers, is replaced by the server's, which is empty on the create form. Enter values with `fill()`, `pressSequentially()` or `check()`, after the wait above.
- After a submit, wait for its result, such as `expect($page->locator('[data-test-sylius-flash-message-type="success"]'))->toBeVisible()`. A rejected form comes back on the same URL, and the Live form shows the errors of the fields it re-rendered before the submit. Wait for the page the server rejected it with, `$page->waitForFunction('() => 422 === performance.getEntriesByType("navigation")[0].responseStatus')`, so the checks don't pass on the page from before the submit.
- Bootstrap scrolls smoothly to an anchor. After clicking a link to `#id`, wait until the target has arrived, such as `$page->waitForFunction('() => Math.abs(document.getElementById("content-delivery").getBoundingClientRect().top - 24) < 2')`.

## Data

Create the data inline (see CLAUDE.md's Tests section), then clear the entity manager before the page reads it: the page then loads the Terms through Doctrine, which sets the locale their translations are read in. A Terms still in the identity map has none, and the page fails with "No locale has been set".

For checkout complete, create a cart with `CreatesCartTrait::createCartReadyToComplete()` and log in as `getShopUser()` on the `shop` firewall. The cart context finds a logged-in customer's cart only if it wasn't created by a guest, which is why the trait sets the customer with `setCustomerWithAuthorization()`. Without that, checkout redirects to the empty cart.

## Assets

The asset bridge serves only the path prefixes in `tests/Application/config/packages/playwright.yaml`. Any other file, such as one in a new folder of the test app's `public/`, is a 404 that fails no test on its own, so assert that an image has loaded (`naturalWidth > 0`) before measuring it.

## Locating

- Bootstrap moves an open modal to the end of the page. Locate it from the page (`.modal.show`), not from the row that opened it.
- `allInnerTexts()` isn't supported. Read several texts with `$page->locator($selector)->evaluateAll('elements => elements.map(element => element.textContent.trim())')`.

## Screenshots

Don't name a test method `screenshot()`, which `PlaywrightTestCase` already has. `$page->screenshot($path)` writes from the Playwright server, which resolves a relative path against its own working directory. Pass an absolute path, such as one in the scratchpad. `['fullPage' => true]` cuts the fixed admin sidebar short. For a whole page, enlarge the viewport with `setViewportSize()` instead.
