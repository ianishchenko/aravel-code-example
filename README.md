# Laravel code example

## In this code fragment we use
- Laravel
- PhpSpreadsheet
- Carbon

## Quick description

It's an example of hourly and daily xls reports. In this code example are described good implementation OOP in PHP.  
There are two interfaces and two abstract classes that we use to simplify reports implementation and clarify reporting generation processes.  
Reports are generated based on the user config stored in the database.   

`ReportXlsInterface.php` - the interface for all xls report files.  
`ReportXlsAbstract.php` - the abstract class with basic functionality implementation.  
`ReportInterface.php` - the interface for all reports file.  
`ReportAbstract.php` - abstract class with basic functionality implementation.  
`Daily` - the directory that contain `Report.php` and `ReportXls.php` files which realised daily report business logic and generate the xls file.  
`Hourly` - the directory that contain `Report.php` and `ReportXls.php` files which realised hourly report business logic and generate the xls file.