postgres-dbal
=============

PostgreSQL database abstraction layer

Introduction
------------

This database abstraction layer for PostgreSQL contains a number of special features. Well, apart from being an abstraction layer that is. An important feature of this library is the transparent use of transactions. Both raw connections (without transactions) and transactional connections implements the DatabaseConnection interface. You can even start transaction "within transactions", which will be converted into 'save points'.

Examples
--------

Although no example files have been added to this library, for now you can checkout the unit test which shows you how to use this library. In the near future, we will add comprehensive example files to make it easier for you to incorporate this library in your own projects.

