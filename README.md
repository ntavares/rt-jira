# rt-jira

Just a small script to import individual RT tickets to JIRA.
The reason for this script is that RT workflows are well established, but it's hard to follow the tickets, especially from Developers.
This ticket helps 1st line support to generate issues to be followed up by Development.

**Usage**
After configuring RT_URL, RT_USER, RT_PASSWORD, JIRA_URL, JIRA_USER, JIRA_PASSWORD, JIRA_TICKET_TYPE, JIRA_WATCHERS, to match your infrastructure, you'll be able to simply type:
```
$ php -f rt-jira.php <RT-ticket-number> <JIRA-project-code>
```
