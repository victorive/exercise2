<?php

use App\Models\Restaurant;
use Carbon\Carbon;

class Exercise2
{
    /**
     * @param Restaurant $restaurant The restaurant object
     * @param Carbon $date The date object
     * @param boolean $ignoreBookingDuration Whether to ignore the booking duration or not (default: false)
     * @return array An array of booking times
     */
    // This method retrieves the service times for a restaurant.
    // It takes in a Restaurant object, a Carbon date object, and a boolean value that specifies whether to ignore the booking duration or not.
    public function getServiceTimesForRestaurant(Restaurant $restaurant, Carbon $date, $ignoreBookingDuration = false)
    {
        // Initialize arrays to store booking times, filtered booking times, and opening hours
        $booking_times = [];
        $filtered_booking_times = [];
        $opening_hours = [];
        // Get the English day of the week from the date (e.g. Tuesday)
        $day = $date->englishDayOfWeek;
        // Get the default booking time step in minutes from the restaurant
        $step = $restaurant->default_booking_time_step_minutes; // ex 15 or 30 minutes
        try {
            // Check if the restaurant has special service hours on the given date (maybe due to a fest or celebration)
            if ($ssh = $restaurant->inSpecialServiceHoursActive($date)) {
                // Get the special service hours for the day using the English day of the week and the ID of the special service hour
                // Hide unnecessary fields like id, day, restaurant_id, created_at, and updated_at using the eloquent makeHidden() method
                $service_hours = $restaurant->specialServiceHours($day, $ssh->id)
                    ->makeHidden('id', 'day', 'restaurant_id', 'created_at', 'updated_at');
            } else {
                // If the restaurant isn't currently running special services hours on the given date,
                // Get the regular service hours for the day, hiding unnecessary fields like
                // Hide unnecessary fields like id, day, restaurant_id, created_at, and updated_at using the eloquent makeHidden() method
                $service_hours = $restaurant->serviceHours($day)->makeHidden('id', 'day', 'restaurant_id', 'created_at', 'updated_at');
            }
        } catch (\Throwable $th) {

            // Catch any exceptions and return an empty array of booking times
            // Instead of throwing an exception message
            return $booking_times;
        }
        // Initialize a variable to skip certain booking times
        $skip = [];

        // Loop through the available service hours for the restaurant
        foreach ($service_hours as $service_hour) {
            //Initialize a counter
            $c++;

            // Format the open and close times to 24-hour format (e.g. 23:20:12)
            $open = Carbon::createFromFormat('H:i:s', $service_hour->open);
            $close = Carbon::createFromFormat('H:i:s', $service_hour->close);

            // Check if the available service hour is restricted to one sitting
            if ($service_hour->enforce_one_sitting) {
                /// Format the opening time to hours and minutes (e.g. 23:20) and add to booking times, then continue execution
                $booking_times[] = $open->format('H:i');
                continue;
            }

            // Check if the booking duration of the available service hour needs to be ignored
            if ($service_hour->ingore_booking_duration) {
                $ignoreBookingDuration = true;
            }

            // If the count of the available service hours is equal to $c, and $ignoreBookingDuration is false
            // Subtract the default booking hours from the restaurant's closing time
            if ($c == count($service_hours) && !$ignoreBookingDuration) {
                $close->subMinutes($restaurant->default_booking_duration_hours);
            }

            // Calculate the difference between the open and closing times of the restaurant
            $diff = $open->diffInMinutes($close);

            // When the calculation is done, push the opening hours formatted in hours and minutes to the booking times array
            $booking_times[] = $open->format('H:i');

            // Run a while loop that checks if the calculated difference between the open and closing hours is greater than 0
            // and the closing hours formatted in minutes is equal to 59 minutes or the opening hours added to the default booking step hours
            // of the restaurant is less than the closing hours
            while ($diff > 0 && ($close->format('i') == '59' || $open->copy()->addMinutes($step)->lte($close))) {
                // Format the opening hours added to the step in hours and minutes and push to the booking times array
                $booking_times[] = $open->addMinutes($step)->format('H:i');

                // Subtract the step from the $diff and set as the new difference
                // If the condition is no longer satisfied, break out of the loop
                $diff -= $step;
            }
        }
        // If the given date instance is today, then add the widget booking minutes before to the first booking time
        if ($date->isToday()) {
            $firstBookingTime = Carbon::now()->addMinutes($restaurant->widget_booking_minutes_before);

            // Loop through the available booking times and using a booking time, create a Carbon datetime instance from it
            // Compare if the first booking time calculated earlier is greater than or equal to the booking time
            // This removes booking times that are less than the first booking time of the day from the array of booking times
            foreach ($booking_times as $idx => $bt) {
                $bt_carbon = Carbon::createFromTimeString($bt);
                if ($firstBookingTime >= $bt_carbon) {
                    unset($booking_times[$idx]);
                }
            }
        }

        // Remove duplicate booking times from the array of booking times and return the unique values
        $booking_times = array_unique($booking_times);

        // Return all the values of the booking times array indexed numerically
        $booking_times = array_values($booking_times);

        // Set the pointer to the last element in the booking times array but passed by reference
        // Check if it is a time equal to 00:00
        if (end($booking_times) == '00:00') {
            // If the time at the end of the array is 00:00, remove it from the array
            // Since it's passed by reference, no need for a new variable to store the resulting array
            array_pop($booking_times);
        }

        // Sort the array of booking times by the values in ascending order (smallest to largest)
        sort($booking_times);

        // Finally, return an array containing the restaurant's booking times
        return $booking_times;
    }
}
