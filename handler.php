<?php

namespace CalProxy;

use ICal\Event;

class handler {

    /**
     * Parse the event and do the replacement and optimizations
     * @param $e Event a single ical event that should be cleaned up
     */
    public static function cleanEvent(Event &$e) {
        $event = new \Eluceo\iCal\Component\Event();

        //Add missing fields if possible
        if (!isset($e['LOCATION'])) {
            $e['LOCATION'] = '';
        }
        if (!isset($e['LOCATIONTITLE'])) {
            $e['LOCATIONTITLE'] = '';
        }
        if (!isset($e['URL'])) {
            $e['URL'] = '';
        }
        if (!isset($e['DESCRIPTION'])) {
            $e['DESCRIPTION'] = '';
        }

        //Strip added slashes by the parser
        $summary = stripcslashes($e->summary);
        $description = stripcslashes($e->description);
        $location = stripcslashes($e->location);

        //Remember the old title in the description
        $event->setDescription($summary . "\n" . $description);
        $event->setLocation($location);

        //Remove the TAG and anything after e.g.: (IN0001)
        $summary = preg_replace('/(\((IN|MA)[0-9]+,?\s?\)*).+/', '', $summary);

        //Some common replacements: yes its a long list
        $searchReplace = [];
        $searchReplace['Tutorübungen'] = 'TÜ';
        $searchReplace['Grundlagen'] = 'G';
        $searchReplace['Datenbanken'] = 'DB';
        $searchReplace['Betriebssysteme und Systemsoftware'] = 'BS';
        $searchReplace['Einführung in die Informatik '] = 'INFO';
        $searchReplace['Praktikum: Grundlagen der Programmierung'] = 'PGP';
        $searchReplace['Einführung in die Rechnerarchitektur'] = 'ERA';
        $searchReplace['Einführung in die Softwaretechnik'] = 'EIST';
        $searchReplace['Algorithmen und Datenstrukturen'] = 'AD';
        $searchReplace['Rechnernetze und Verteilte Systeme'] = 'RNVS';
        $searchReplace['Einführung in die Theoretische Informatik'] = 'THEO';
        $searchReplace['Diskrete Strukturen'] = 'DS';
        $searchReplace['Diskrete Wahrscheinlichkeitstheorie'] = 'DWT';
        $searchReplace['Numerisches Programmieren'] = 'NumProg';
        $searchReplace['Lineare Algebra für Informatik'] = 'LinAlg';
        $searchReplace['Analysis für Informatik'] = 'Analysis';
        $searchReplace[' der Künstlichen Intelligenz'] = 'KI';
        $searchReplace['Advanced Topics of Software Engineering'] = 'ASE';
        $searchReplace['Praktikum - iPraktikum, iOS Praktikum'] = 'iPraktikum';

        //Do the replacement
        $summary = strtr($summary, $searchReplace);

        //Remove some stuff which is not really needed
        $summary = str_replace(['Standardgruppe', 'PR, ', 'VO, ', 'FA, '], '', $summary);

        //Try to make sense out of the location
        if (!empty($location)) {
            if (strpos($location, '(56') !== false) {
                // Informatik
                self::switchLocation($event, $location, 'Boltzmannstraße 3, 85748 Garching bei München');
            } else if (strpos($location, '(55') !== false) {
                // Maschbau
                self::switchLocation($event, $location, 'Boltzmannstraße 15, 85748 Garching bei München');
            } else if (strpos($location, '(81') !== false) {
                // Hochbrück
                self::switchLocation($event, $location, 'Parkring 11-13, 85748 Garching bei München');
            } else if (strpos($location, '(51') !== false) {
                // Physik
                self::switchLocation($event, $location, 'James-Franck-Straße 1, 85748 Garching bei München');
            }
        }

        //Check status
        switch ($e['STATUS']) {
            default:
            case 'CONFIRMED':
                $e['STATUS'] = \Eluceo\iCal\Component\Event::STATUS_CONFIRMED;
                break;
            case 'CANCELLED':
                $e['STATUS'] = \Eluceo\iCal\Component\Event::STATUS_CANCELLED;
                break;
            case 'TENTATIVE':
                $e['STATUS'] = \Eluceo\iCal\Component\Event::STATUS_TENTATIVE;
                break;
        }

        //Add all fields
        $event->setUniqueId($e->uid)
            ->setDtStamp(new \DateTime($e->dtstamp))
            ->setStatus($e->status)
            //->setUrl($e->)
            ->setSummary($summary)
            ->setDtStart(new \DateTime($e->dtstart))
            ->setDtEnd(new \DateTime($e->dtend));

        return $event;
    }


    /**
     * Update the location field
     *
     * @param $e array element to be edited
     * @param $newLoc string new location that should be set to the element
     */
    public static function switchLocation(\Eluceo\iCal\Component\Event &$e, $oldLocation, $newLoc) {
        $e->setDescription($oldLocation . "\n" . $e->getDescription());
        $e->setLocation($newLoc, $oldLocation);
    }

    /**
     * Remove duplicate entries: events that happen at the same time in multiple locations
     * @param $events
     */
    public static function noDupes(array &$events) {
        //Sort them
        usort($events, function (Event $a, Event $b) {
            if (strtotime($a->dtstart) > strtotime($b->dtstart)) {
                return 1;
            } else if ($a->dtstart > $b->dtstart) {
                return -1;
            }

            return 0;
        });

        //Find dupes
        $total = count($events);
        for ($i = 1; $i < $total; $i++) {
            //Check if start time, end time and title match then merge
            if ($events[ $i - 1 ]->dtstart === $events[ $i ]->dtstart
                && $events[ $i - 1 ]->dtend === $events[ $i ]->dtend
                && $events[ $i - 1 ]->summary === $events[ $i ]->summary) {
                //Append the location to the next (same) element
                $events[ $i ]->location .= "\n" . $events[ $i - 1 ]->location;

                //Mark this element for removal
                unset($events[ $i - 1 ]);
            }
        }
    }
}