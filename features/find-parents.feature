Feature: Class/Interface navigation
    As a user
    I want to receive the parent class of current file
    So I can navigate to it.

    Scenario: Current class only extends a class
        Given there is a file with:
        """
        <?php
        namespace Foo;
        class SomeParentClass
        {
        }
        class SomeChildClass extends SomeParentClass
        {

        }
        """
        When I move my cursor to line 8
        And I ask for the parents of current class
        Then I should get following parents:
            | name            | fqcn                | file |
            | SomeParentClass | Foo\SomeParentClass |      |


    Scenario: Current class extends class and implements interface
        Given there is a file with:
        """
        <?php
        namespace Foo;
        class SomeParentClass
        {
        }
        interface SomeInterface
        {
          public function aMethod();
        }
        class SomeChildClass extends SomeParentClass implements SomeInterface
        {
        public function aMethod() {

        }
        }
        """
        When I move my cursor to line 13
        And I ask for the parents of current class
        Then I should get following parents:
            | name            | fqcn                | file |
            | SomeParentClass | Foo\SomeParentClass |      |
            | SomeInterface   | Foo\SomeInterface   |      |
