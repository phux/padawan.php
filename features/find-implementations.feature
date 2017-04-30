Feature: Class/Interface navigation
    As a user
    I want to receive the parent class of current file
    So I can navigate to it.

    Scenario: Current class only extends a class
        Given there is a file with:
        """
        <?php
        class SomeChildClass extends SomeParentClass
        {
        }
        class SomeParentClass
        {
        }
        """
        When I move my cursor to line 5
        And I ask for implementations
        Then I should get following children:
            | name           | fqcn           | file |
            | SomeChildClass | SomeChildClass |      |
