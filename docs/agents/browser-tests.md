# Browser tests

How to write browser tests (`tests/Browser/`) that pass every run. They use playwright-symfony: Chromium sends each request to the test's own PHP process, which handles it in the test's kernel, inside the test's DAMA transaction.

## Waiting

The PHP process serves the page's requests only while it is inside a Playwright call. Wait through Playwright: `waitForLoadState()`, `waitForResponse()`, `waitForFunction()` and `expect()` from `Playwright\Testing`. A `usleep()` stalls the page, because nothing answers its requests while PHP sleeps.

- After `visit()`, call `$page->waitForLoadState('networkidle')` before the first click, so Bootstrap and the Live components have bound their handlers.
- A changed field re-renders the Live form. Before a submit, wait for that re-render, or the submit's navigation can abort. Run the change inside the wait:

    ```php
    $page->waitForResponse('**/_components/**', ['action' => 'document.querySelector(\'[data-test-label="de_DE"]\').blur()']);
    $page->waitForFunction('() => null === document.querySelector("[data-controller~=live][busy]")');
    ```

- After a submit, wait for its result, such as `expect($page->locator('[data-test-sylius-flash-message-type="success"]'))->toBeVisible()`.

## Data

Create the data inline (see CLAUDE.md's Tests section), then clear the entity manager before the page reads it: the page then loads the Terms through Doctrine, which sets the locale their translations are read in. A Terms still in the identity map has none, and the page fails with "No locale has been set".

## Locating

- Bootstrap moves an open modal to the end of the page. Locate it from the page (`.modal.show`), not from the row that opened it.
- `allInnerTexts()` isn't supported. Read several texts with `$page->locator($selector)->evaluateAll('elements => elements.map(element => element.textContent.trim())')`.

## Screenshots

`$page->screenshot($path)` writes from the Playwright server, which resolves a relative path against its own working directory. Pass an absolute path, such as one in the scratchpad. `['fullPage' => true]` cuts the fixed admin sidebar short. For a whole page, enlarge the viewport with `setViewportSize()` instead.
